<?php
if (!defined('ABSPATH')) { exit; }

final class Elev8_OS_Glass_Catalog_Import_Service {
    private const TRANSIENT_PREFIX = 'elev8_glass_catalog_wizard_';
    private const USER_META_KEY = '_elev8_glass_catalog_wizard_session';

    public static function session_key(): string {
        return self::TRANSIENT_PREFIX . get_current_user_id();
    }

    public static function session(): array {
        $value = get_transient(self::session_key());
        if (is_array($value) && !empty($value)) {
            return $value;
        }

        $stored = get_user_meta(get_current_user_id(), self::USER_META_KEY, true);
        return is_array($stored) ? $stored : [];
    }

    public static function clear(): void {
        delete_transient(self::session_key());
        delete_user_meta(get_current_user_id(), self::USER_META_KEY);
    }

    public static function max_upload_label(): string {
        return size_format((int) wp_max_upload_size());
    }

    public static function upload_and_parse(array $file): array|WP_Error {
        $upload_error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($upload_error !== UPLOAD_ERR_OK) {
            return new WP_Error('elev8_wizard_upload', self::upload_error_message($upload_error));
        }
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('elev8_wizard_upload', 'The workbook upload was not received by WordPress. Please choose the file again.');
        }

        $name = sanitize_file_name($file['name'] ?? 'glass-production.xlsx');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xlsm'], true)) {
            return new WP_Error('elev8_wizard_type', 'Upload an .xlsx or .xlsm workbook.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $handled = wp_handle_upload($file, [
            'test_form' => false,
            // The extension was validated above. Some servers identify Excel files as application/zip.
            'test_type' => false,
        ]);
        if (!empty($handled['error'])) {
            return new WP_Error('elev8_wizard_store', 'WordPress could not store the workbook: ' . $handled['error']);
        }

        $parsed = self::parse_workbook($handled['file']);
        if (is_wp_error($parsed)) {
            return $parsed;
        }

        $parsed['file_url'] = $handled['url'];
        $parsed['file_path'] = $handled['file'];
        $parsed['file_name'] = $name;
        $parsed['file_size'] = isset($file['size']) ? (int) $file['size'] : 0;
        $parsed['uploaded_at'] = current_time('mysql');

        // Store in both places. User meta is the durable fallback when a host/object cache drops large transients.
        set_transient(self::session_key(), $parsed, DAY_IN_SECONDS * 7);
        update_user_meta(get_current_user_id(), self::USER_META_KEY, $parsed);

        return $parsed;
    }

    private static function upload_error_message(int $code): string {
        $max = self::max_upload_label();
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The workbook is larger than this site allows. Current upload limit: ' . $max . '.',
            UPLOAD_ERR_PARTIAL => 'The workbook upload stopped before it finished. Please try again.',
            UPLOAD_ERR_NO_FILE => 'Choose the production workbook before clicking Analyze workbook.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server is missing its temporary upload folder.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded workbook to disk.',
            UPLOAD_ERR_EXTENSION => 'A server extension stopped the workbook upload.',
            default => 'The workbook upload failed with error code ' . $code . '.',
        };
    }

    private static function parse_workbook(string $path): array|WP_Error {
        $archive = self::open_archive($path);
        if (is_wp_error($archive)) {
            return $archive;
        }
        [$read, $cleanup] = $archive;

        $diagnostics = [
            'parser_version' => '14.0.1',
            'workbook_opened' => true,
            'sheet_found' => false,
            'shared_strings' => 0,
            'direct_cells' => 0,
            'merged_ranges' => 0,
            'max_column' => 'A',
            'detected_rows' => [],
            'warnings' => [],
            'skipped_columns' => 0,
        ];

        $shared = self::read_shared_strings($read('xl/sharedStrings.xml'));
        $diagnostics['shared_strings'] = count($shared);

        $workbook = $read('xl/workbook.xml');
        $rels = $read('xl/_rels/workbook.xml.rels');
        if (!$workbook || !$rels) {
            $cleanup();
            return new WP_Error('elev8_wizard_structure', 'Workbook structure is unavailable. The file may not be a valid Excel workbook.');
        }

        $sheet_path = self::find_sheet_path($workbook, $rels, 'Production Information');
        if ($sheet_path === '') {
            $cleanup();
            return new WP_Error('elev8_wizard_sheet', 'The workbook does not contain a sheet named Production Information.');
        }
        $diagnostics['sheet_found'] = true;
        $diagnostics['sheet_path'] = $sheet_path;

        $sheet_xml = $read($sheet_path);
        if (!$sheet_xml) {
            $cleanup();
            return new WP_Error('elev8_wizard_sheet_read', 'The Production Information sheet was found but could not be read.');
        }

        $direct_cells = self::read_cells($sheet_xml, $shared);
        $merge_ranges = self::read_merge_ranges($sheet_xml);
        $expanded_cells = self::expand_merged_cells($direct_cells, $merge_ranges);

        $diagnostics['direct_cells'] = count($direct_cells);
        $diagnostics['merged_ranges'] = count($merge_ranges);

        $max_col_num = 0;
        foreach (array_keys($expanded_cells) as $ref) {
            [$col] = self::split_ref($ref);
            $max_col_num = max($max_col_num, self::col_num($col));
        }
        $diagnostics['max_column'] = self::num_col($max_col_num);

        $rows = self::detect_rows($direct_cells, $expanded_cells, $merge_ranges, $max_col_num);
        $diagnostics['detected_rows'] = $rows;

        if (empty($rows['actual_retail']) || empty($rows['blower_pay']) || empty($rows['family'])) {
            $cleanup();
            return new WP_Error(
                'elev8_wizard_layout',
                'Elev8 OS opened the workbook but could not identify the product heading, Actual Retail, and Blower Pay rows. Detected rows: ' . self::row_diagnostic_text($rows)
            );
        }

        [$families, $items, $build_diagnostics] = self::build_catalog(
            $direct_cells,
            $expanded_cells,
            $merge_ranges,
            $rows,
            $max_col_num
        );
        $diagnostics = array_merge($diagnostics, $build_diagnostics);

        $cleanup();

        if (empty($items)) {
            return new WP_Error(
                'elev8_wizard_empty',
                'The workbook was opened and the important rows were found, but no production items were detected. ' . self::row_diagnostic_text($rows)
            );
        }

        ksort($families, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'sheet' => 'Production Information',
            'families' => $families,
            'items' => $items,
            'family_count' => count($families),
            'item_count' => count($items),
            'diagnostics' => $diagnostics,
        ];
    }

    private static function open_archive(string $path): array|WP_Error {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($path) !== true) {
                return new WP_Error('elev8_wizard_open', 'Elev8 OS could not open the workbook archive.');
            }
            $read = static function (string $name) use ($zip): string {
                $value = $zip->getFromName($name);
                return $value === false ? '' : (string) $value;
            };
            $cleanup = static function () use ($zip): void { $zip->close(); };
            return [$read, $cleanup];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        $uploads = wp_upload_dir();
        $temp = trailingslashit($uploads['basedir']) . 'elev8-catalog-wizard-' . wp_generate_uuid4();
        wp_mkdir_p($temp);
        $unzipped = unzip_file($path, $temp);
        if (is_wp_error($unzipped)) {
            return new WP_Error('elev8_wizard_zip', 'The server could not read the Excel workbook: ' . $unzipped->get_error_message());
        }
        $read = static function (string $name) use ($temp): string {
            $file = trailingslashit($temp) . $name;
            return is_readable($file) ? (string) file_get_contents($file) : '';
        };
        $cleanup = static function () use ($temp): void { self::delete_tree($temp); };
        return [$read, $cleanup];
    }

    private static function read_shared_strings(string $xml): array {
        $shared = [];
        if ($xml && preg_match_all('/<si\b[^>]*>(.*?)<\/si>/si', $xml, $matches)) {
            foreach ($matches[1] as $si) {
                $text = '';
                if (preg_match_all('/<t\b[^>]*>(.*?)<\/t>/si', $si, $texts)) {
                    foreach ($texts[1] as $value) {
                        $text .= self::xml_text($value);
                    }
                }
                $shared[] = $text;
            }
        }
        return $shared;
    }

    private static function find_sheet_path(string $workbook, string $rels, string $sheet_name): string {
        $relmap = [];
        if (preg_match_all('/<Relationship\b([^>]*)\/?\s*>/si', $rels, $matches)) {
            foreach ($matches[1] as $attrs) {
                $id = self::xml_attr($attrs, 'Id');
                $target = self::xml_attr($attrs, 'Target');
                if ($id !== '') {
                    $relmap[$id] = $target;
                }
            }
        }

        if (preg_match_all('/<sheet\b([^>]*)\/?\s*>/si', $workbook, $matches)) {
            foreach ($matches[1] as $attrs) {
                if (strcasecmp(self::xml_attr($attrs, 'name'), $sheet_name) !== 0) {
                    continue;
                }
                $rid = self::xml_attr($attrs, 'r:id');
                $target = (string) ($relmap[$rid] ?? '');
                if ($target === '') {
                    return '';
                }
                $target = ltrim($target, '/');
                return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
            }
        }
        return '';
    }

    private static function read_cells(string $sheet_xml, array $shared): array {
        $cells = [];

        // Avoid running one large PCRE expression across this very wide worksheet.
        // Split the XML at each cell boundary and parse each small cell fragment independently.
        $cell_blocks = preg_split('/(?=<c\b)/i', $sheet_xml);
        if (!is_array($cell_blocks)) {
            return $cells;
        }

        foreach ($cell_blocks as $cell_block) {
            $cell_end = stripos($cell_block, '</c>');
            if ($cell_end === false) {
                continue;
            }
            $cell_xml = substr($cell_block, 0, $cell_end + 4);
            if (!preg_match('/^<c\b([^>]*)>(.*)<\/c>$/si', $cell_xml, $match)) {
                continue;
            }

            $ref = self::xml_attr($match[1], 'r');
            if ($ref === '') {
                continue;
            }
            $type = self::xml_attr($match[1], 't');
            $body = $match[2];
            $formula = '';
            if (preg_match('/<f\b[^>]*>(.*?)<\/f>/si', $body, $formula_match)) {
                $formula = self::xml_text($formula_match[1]);
            }

            $value = '';
            if ($type === 'inlineStr') {
                if (preg_match_all('/<t\b[^>]*>(.*?)<\/t>/si', $body, $texts)) {
                    foreach ($texts[1] as $text) {
                        $value .= self::xml_text($text);
                    }
                }
            } elseif (preg_match('/<v\b[^>]*>(.*?)<\/v>/si', $body, $value_match)) {
                $raw = self::xml_text($value_match[1]);
                $value = $type === 's' ? (string) ($shared[(int) $raw] ?? '') : $raw;
            }

            $cells[$ref] = [
                'value' => $value,
                'formula' => $formula,
                'direct' => true,
            ];
        }
        return $cells;
    }

    private static function read_merge_ranges(string $sheet_xml): array {
        $ranges = [];
        if (preg_match_all('/<mergeCell\b[^>]*ref="([^"]+)"[^>]*\/?\s*>/si', $sheet_xml, $matches)) {
            foreach ($matches[1] as $range) {
                [$start, $end] = array_pad(explode(':', $range), 2, $range);
                [$start_col, $start_row] = self::split_ref($start);
                [$end_col, $end_row] = self::split_ref($end);
                $ranges[] = [
                    'start_ref' => $start,
                    'end_ref' => $end,
                    'start_col' => self::col_num($start_col),
                    'end_col' => self::col_num($end_col),
                    'start_row' => $start_row,
                    'end_row' => $end_row,
                ];
            }
        }
        return $ranges;
    }

    private static function expand_merged_cells(array $direct_cells, array $ranges): array {
        $cells = $direct_cells;
        foreach ($ranges as $range) {
            $source = $direct_cells[$range['start_ref']] ?? null;
            if (!$source || trim((string) $source['value']) === '') {
                continue;
            }
            for ($row = $range['start_row']; $row <= $range['end_row']; $row++) {
                for ($col = $range['start_col']; $col <= $range['end_col']; $col++) {
                    $ref = self::num_col($col) . $row;
                    if (!isset($cells[$ref])) {
                        $cells[$ref] = $source;
                        $cells[$ref]['direct'] = false;
                        $cells[$ref]['merge_source'] = $range['start_ref'];
                    }
                }
            }
        }
        return $cells;
    }

    private static function detect_rows(array $direct_cells, array $expanded_cells, array $ranges, int $max_col): array {
        $labels = [
            'actual_retail' => ['actual retail'],
            'dist_profit_at_retail' => ['dist profit @ retail', 'dist profit at retail'],
            'dist_additional_cost' => ['dist additonal cost', 'dist additional cost'],
            'suggested_retail' => ['suggested retail'],
            'dist_profit_wholesale' => ['dist profit (ws)', 'dist profit ws'],
            'premier_profit' => ['premier profit'],
            'actual_wholesale' => ['actual ws', 'actual wholesale'],
            'suggested_wholesale' => ['suggested wholesale price', 'suggested wholesale'],
            'sold_to_distributor_at' => ['sold to dist @', 'sold to distributor @'],
            'blower_pay' => ['blower pay'],
            'material_cost' => ['material cost'],
            'total_cost' => ['total cost'],
            'video' => ['video link'],
            'estimated_minutes' => ['production time per part'],
        ];

        $rows = [];
        foreach ($direct_cells as $ref => $cell) {
            [$col, $row] = self::split_ref($ref);
            if ($row > 60 || self::col_num($col) > self::col_num('P')) {
                continue;
            }
            $normalized = self::normalize_label((string) $cell['value']);
            if ($normalized === '') {
                continue;
            }
            foreach ($labels as $key => $aliases) {
                foreach ($aliases as $alias) {
                    if ($normalized === self::normalize_label($alias)) {
                        $rows[$key] = $row;
                        break 2;
                    }
                }
            }
        }

        $retail_row = (int) ($rows['actual_retail'] ?? 0);
        if ($retail_row > 0) {
            $best_row = 0;
            $best_score = -1;
            $start = max(1, $retail_row - 6);
            for ($row = $start; $row < $retail_row; $row++) {
                $merge_span = 0;
                foreach ($ranges as $range) {
                    if ($range['start_row'] === $row && $range['end_row'] === $row && $range['end_col'] >= self::col_num('J')) {
                        $merge_span += max(1, $range['end_col'] - max($range['start_col'], self::col_num('J')) + 1);
                    }
                }
                $text_count = 0;
                for ($col = self::col_num('J'); $col <= $max_col; $col++) {
                    $value = trim((string) ($direct_cells[self::num_col($col) . $row]['value'] ?? ''));
                    if ($value !== '' && !is_numeric($value)) {
                        $text_count++;
                    }
                }
                $score = ($merge_span * 10) + $text_count;
                if ($score > $best_score) {
                    $best_score = $score;
                    $best_row = $row;
                }
            }
            if ($best_row > 0) {
                $rows['family'] = $best_row;
            }
        }

        $family_row = (int) ($rows['family'] ?? 0);
        if ($family_row > 0) {
            $best_method_row = 0;
            $best_method_count = 0;
            for ($row = max(1, $family_row - 4); $row < $family_row; $row++) {
                $count = 0;
                for ($col = self::col_num('J'); $col <= $max_col; $col++) {
                    $number = self::number(self::cell_value($expanded_cells, self::num_col($col) . $row));
                    if ($number !== null && in_array((int) $number, [1, 2, 3, 4], true)) {
                        $count++;
                    }
                }
                if ($count > $best_method_count) {
                    $best_method_count = $count;
                    $best_method_row = $row;
                }
            }
            if ($best_method_row > 0) {
                $rows['method'] = $best_method_row;
            }
        }

        // The instructions row is usually the first text-heavy row after Total Cost and before Video Link.
        $total_row = (int) ($rows['total_cost'] ?? 0);
        $video_row = (int) ($rows['video'] ?? 0);
        if ($total_row > 0) {
            $end = $video_row > $total_row ? $video_row - 1 : $total_row + 8;
            $best_row = 0;
            $best_count = 0;
            for ($row = $total_row + 1; $row <= $end; $row++) {
                $count = 0;
                for ($col = self::col_num('J'); $col <= $max_col; $col++) {
                    $value = trim((string) self::cell_value($direct_cells, self::num_col($col) . $row));
                    if ($value !== '' && !is_numeric($value)) {
                        $count++;
                    }
                }
                if ($count > $best_count) {
                    $best_count = $count;
                    $best_row = $row;
                }
            }
            if ($best_row > 0) {
                $rows['instructions'] = $best_row;
            }
        }

        return $rows;
    }

    private static function build_catalog(
        array $direct_cells,
        array $expanded_cells,
        array $ranges,
        array $rows,
        int $max_col
    ): array {
        $family_row = (int) $rows['family'];
        $retail_row = (int) $rows['actual_retail'];
        $method_row = (int) ($rows['method'] ?? 0);
        $start_col = self::col_num('J');

        $blocks = self::family_blocks($direct_cells, $expanded_cells, $ranges, $family_row, $start_col, $max_col);
        $families = [];
        $items = [];
        $skipped = 0;
        $warnings = [];

        foreach ($blocks as $block) {
            $family = trim((string) $block['family']);
            if ($family === '') {
                continue;
            }

            $segments = self::item_segments($direct_cells, $expanded_cells, $ranges, $block, $family_row, $retail_row);
            foreach ($segments as $segment) {
                $source_col_num = (int) $segment['start_col'];
                $source_col = self::num_col($source_col_num);
                $alternate_cols = [];
                for ($col = $segment['start_col'] + 1; $col <= $segment['end_col']; $col++) {
                    $alternate_cols[] = self::num_col($col);
                }

                $labels = array_values(array_filter(array_map('trim', $segment['labels'])));
                $labels = self::unique_text($labels);
                $name_parts = array_merge([$family], $labels);
                $name_parts = self::unique_text($name_parts);
                $catalog_name = implode(' — ', $name_parts);

                $financial = [];
                foreach (self::financial_row_keys() as $key) {
                    $row = (int) ($rows[$key] ?? 0);
                    $financial[$key] = $row > 0
                        ? self::first_numeric_in_segment($expanded_cells, $row, $segment['start_col'], $segment['end_col'])
                        : null;
                }

                $estimated_minutes = !empty($rows['estimated_minutes'])
                    ? self::first_numeric_in_segment($expanded_cells, (int) $rows['estimated_minutes'], $segment['start_col'], $segment['end_col'])
                    : null;
                $method_code = $method_row > 0
                    ? self::first_numeric_in_segment($expanded_cells, $method_row, $segment['start_col'], $segment['end_col'])
                    : null;
                $instructions = !empty($rows['instructions'])
                    ? self::first_text_in_segment($direct_cells, (int) $rows['instructions'], $segment['start_col'], $segment['end_col'])
                    : '';
                $video_url = !empty($rows['video'])
                    ? self::first_hyperlink_in_segment($direct_cells, (int) $rows['video'], $segment['start_col'], $segment['end_col'])
                    : '';

                $has_direct_identity = !empty($segment['has_direct_identity']);
                $has_data = self::has_meaningful_data($financial, $estimated_minutes, $instructions, $video_url);
                if (!$has_direct_identity && !$has_data) {
                    $skipped += ($segment['end_col'] - $segment['start_col'] + 1);
                    continue;
                }

                $aliases = self::build_aliases($family, $labels, $catalog_name);
                $alternate_tiers = self::alternate_pay_tiers(
                    $expanded_cells,
                    $rows,
                    $segment['start_col'],
                    $segment['end_col']
                );

                $item_key = $source_col;
                $items[$item_key] = [
                    'source_column' => $source_col,
                    'alternate_source_columns' => implode(', ', $alternate_cols),
                    'family' => $family,
                    'subtype' => $labels[0] ?? '',
                    'variant' => count($labels) > 1 ? end($labels) : '',
                    'catalog_name' => $catalog_name,
                    'search_aliases' => implode(', ', $aliases),
                    'product_code' => 'WB-' . $source_col,
                    'compensation_method' => self::method_from_code($method_code),
                    'piecework_unit' => 'piece',
                    'blower_pay' => $financial['blower_pay'] ?? 0,
                    'estimated_minutes' => $estimated_minutes ?? 0,
                    'actual_retail' => $financial['actual_retail'] ?? 0,
                    'dist_profit_at_retail' => $financial['dist_profit_at_retail'] ?? 0,
                    'dist_additional_cost' => $financial['dist_additional_cost'] ?? 0,
                    'suggested_retail' => $financial['suggested_retail'] ?? 0,
                    'dist_profit_wholesale' => $financial['dist_profit_wholesale'] ?? 0,
                    'premier_profit' => $financial['premier_profit'] ?? 0,
                    'actual_wholesale' => $financial['actual_wholesale'] ?? 0,
                    'suggested_wholesale' => $financial['suggested_wholesale'] ?? 0,
                    'sold_to_distributor_at' => $financial['sold_to_distributor_at'] ?? 0,
                    'material_cost' => $financial['material_cost'] ?? 0,
                    'total_cost' => $financial['total_cost'] ?? 0,
                    'instructions' => $instructions,
                    'video_url' => $video_url,
                    'source_sheet' => 'Production Information',
                    'review_status' => count($labels) === 0 ? 'review' : 'ready',
                    'alternate_pay_tiers' => $alternate_tiers,
                ];
                $families[$family][] = $item_key;
            }
        }

        if (count($blocks) < 2) {
            $warnings[] = 'Only one product-family block was detected. Review the detected heading row.';
        }

        return [$families, $items, [
            'family_blocks' => count($blocks),
            'skipped_columns' => $skipped,
            'warnings' => $warnings,
        ]];
    }

    private static function family_blocks(
        array $direct_cells,
        array $expanded_cells,
        array $ranges,
        int $family_row,
        int $start_col,
        int $max_col
    ): array {
        $blocks = [];
        $covered = [];

        foreach ($ranges as $range) {
            if ($range['start_row'] !== $family_row || $range['end_row'] !== $family_row || $range['end_col'] < $start_col) {
                continue;
            }
            $start = max($start_col, $range['start_col']);
            $family = trim((string) self::cell_value($expanded_cells, self::num_col($start) . $family_row));
            if ($family === '') {
                continue;
            }
            $blocks[] = ['start_col' => $start, 'end_col' => $range['end_col'], 'family' => $family];
            for ($col = $start; $col <= $range['end_col']; $col++) {
                $covered[$col] = true;
            }
        }

        for ($col = $start_col; $col <= $max_col; $col++) {
            if (isset($covered[$col])) {
                continue;
            }
            $ref = self::num_col($col) . $family_row;
            $family = trim((string) self::cell_value($direct_cells, $ref));
            if ($family === '' || is_numeric($family)) {
                continue;
            }
            $end = $col;
            for ($next = $col + 1; $next <= $max_col; $next++) {
                $next_value = trim((string) self::cell_value($direct_cells, self::num_col($next) . $family_row));
                if ($next_value !== '') {
                    break;
                }
                $end = $next;
            }
            $blocks[] = ['start_col' => $col, 'end_col' => $end, 'family' => $family];
            $col = $end;
        }

        usort($blocks, static fn(array $a, array $b): int => $a['start_col'] <=> $b['start_col']);
        return $blocks;
    }

    private static function item_segments(
        array $direct_cells,
        array $expanded_cells,
        array $ranges,
        array $block,
        int $family_row,
        int $retail_row
    ): array {
        $segments = [[
            'start_col' => $block['start_col'],
            'end_col' => $block['end_col'],
            'labels' => [],
            'has_direct_identity' => true,
        ]];

        // Header layers may include family, subgroup and variant rows. Actual Retail can contain text variants in some blocks.
        for ($row = $family_row + 1; $row <= $retail_row; $row++) {
            $layer = self::header_layer_segments($direct_cells, $ranges, $row, $block['start_col'], $block['end_col']);
            if (empty($layer)) {
                continue;
            }

            $new_segments = [];
            foreach ($segments as $segment) {
                $overlaps = [];
                foreach ($layer as $part) {
                    $start = max($segment['start_col'], $part['start_col']);
                    $end = min($segment['end_col'], $part['end_col']);
                    if ($start <= $end) {
                        $overlaps[] = [
                            'start_col' => $start,
                            'end_col' => $end,
                            'label' => $part['label'],
                            'direct' => $part['direct'],
                        ];
                    }
                }

                if (empty($overlaps)) {
                    $new_segments[] = $segment;
                    continue;
                }

                // One label beginning at the block start with no competing labels describes the entire segment.
                if (count($overlaps) === 1) {
                    $part = $overlaps[0];
                    $segment['labels'][] = $part['label'];
                    $segment['has_direct_identity'] = $segment['has_direct_identity'] || $part['direct'];
                    $new_segments[] = $segment;
                    continue;
                }

                foreach ($overlaps as $part) {
                    $child = $segment;
                    $child['start_col'] = $part['start_col'];
                    $child['end_col'] = $part['end_col'];
                    $child['labels'][] = $part['label'];
                    $child['has_direct_identity'] = $child['has_direct_identity'] || $part['direct'];
                    $new_segments[] = $child;
                }
            }
            $segments = $new_segments;
        }

        // Remove duplicate segments created by repeated merged labels.
        $deduped = [];
        foreach ($segments as $segment) {
            $segment['labels'] = self::unique_text($segment['labels']);
            $key = $segment['start_col'] . ':' . $segment['end_col'] . ':' . implode('|', $segment['labels']);
            $deduped[$key] = $segment;
        }
        return array_values($deduped);
    }

    private static function header_layer_segments(array $direct_cells, array $ranges, int $row, int $start_col, int $end_col): array {
        $parts = [];
        $covered = [];

        foreach ($ranges as $range) {
            if ($range['start_row'] !== $row || $range['end_row'] !== $row) {
                continue;
            }
            $start = max($start_col, $range['start_col']);
            $end = min($end_col, $range['end_col']);
            if ($start > $end) {
                continue;
            }
            $label = trim((string) self::cell_value($direct_cells, self::num_col($range['start_col']) . $row));
            if ($label === '' || is_numeric($label)) {
                continue;
            }
            $parts[] = ['start_col' => $start, 'end_col' => $end, 'label' => $label, 'direct' => true];
            for ($col = $start; $col <= $end; $col++) {
                $covered[$col] = true;
            }
        }

        for ($col = $start_col; $col <= $end_col; $col++) {
            if (isset($covered[$col])) {
                continue;
            }
            $label = trim((string) self::cell_value($direct_cells, self::num_col($col) . $row));
            if ($label === '' || is_numeric($label)) {
                continue;
            }
            $parts[] = ['start_col' => $col, 'end_col' => $col, 'label' => $label, 'direct' => true];
        }

        usort($parts, static fn(array $a, array $b): int => $a['start_col'] <=> $b['start_col']);

        // Collapse adjacent cells carrying the same label. In the source workbook these often
        // represent alternate pay tiers for one product, not six duplicate catalog items.
        $collapsed = [];
        foreach ($parts as $part) {
            $last_index = count($collapsed) - 1;
            if ($last_index >= 0) {
                $last = $collapsed[$last_index];
                if (
                    strtolower(trim($last['label'])) === strtolower(trim($part['label']))
                    && $last['end_col'] + 1 === $part['start_col']
                ) {
                    $collapsed[$last_index]['end_col'] = $part['end_col'];
                    $collapsed[$last_index]['direct'] = $last['direct'] || $part['direct'];
                    continue;
                }
            }
            $collapsed[] = $part;
        }
        return $collapsed;
    }

    private static function financial_row_keys(): array {
        return [
            'actual_retail',
            'dist_profit_at_retail',
            'dist_additional_cost',
            'suggested_retail',
            'dist_profit_wholesale',
            'premier_profit',
            'actual_wholesale',
            'suggested_wholesale',
            'sold_to_distributor_at',
            'blower_pay',
            'material_cost',
            'total_cost',
        ];
    }

    private static function first_numeric_in_segment(array $cells, int $row, int $start_col, int $end_col): ?float {
        for ($col = $start_col; $col <= $end_col; $col++) {
            $number = self::number(self::cell_value($cells, self::num_col($col) . $row));
            if ($number !== null) {
                return $number;
            }
        }
        return null;
    }

    private static function first_text_in_segment(array $cells, int $row, int $start_col, int $end_col): string {
        for ($col = $start_col; $col <= $end_col; $col++) {
            $value = trim((string) self::cell_value($cells, self::num_col($col) . $row));
            if ($value !== '' && !is_numeric($value)) {
                return $value;
            }
        }
        return '';
    }

    private static function first_hyperlink_in_segment(array $cells, int $row, int $start_col, int $end_col): string {
        for ($col = $start_col; $col <= $end_col; $col++) {
            $cell = $cells[self::num_col($col) . $row] ?? [];
            $formula = (string) ($cell['formula'] ?? '');
            if (preg_match('/HYPERLINK\s*\(\s*"([^"]+)"/i', $formula, $match)) {
                return esc_url_raw(self::xml_text($match[1]));
            }
        }
        return '';
    }

    private static function alternate_pay_tiers(array $cells, array $rows, int $start_col, int $end_col): array {
        $tiers = [];
        $pay_row = (int) ($rows['blower_pay'] ?? 0);
        if ($pay_row <= 0 || $end_col <= $start_col) {
            return $tiers;
        }
        for ($col = $start_col; $col <= $end_col; $col++) {
            $pay = self::number(self::cell_value($cells, self::num_col($col) . $pay_row));
            if ($pay !== null) {
                $tiers[self::num_col($col)] = $pay;
            }
        }
        return $tiers;
    }

    private static function has_meaningful_data(array $financial, ?float $estimated_minutes, string $instructions, string $video_url): bool {
        foreach ($financial as $value) {
            if ($value !== null && abs($value) > 0.000001) {
                return true;
            }
        }
        return ($estimated_minutes !== null && $estimated_minutes > 0) || $instructions !== '' || $video_url !== '';
    }

    private static function build_aliases(string $family, array $labels, string $catalog_name): array {
        $aliases = [$family, $catalog_name];
        foreach ($labels as $label) {
            $aliases[] = $label;
            $aliases[] = $family . ' ' . $label;
        }
        $family_lower = strtolower($family);
        if (str_contains($family_lower, 'knob')) {
            $aliases[] = 'knob';
            $aliases[] = 'color knob';
            if (str_contains($family_lower, 'push')) {
                $aliases[] = 'push knob';
                $aliases[] = 'decorate knob';
            }
            if (str_contains($family_lower, 'custom')) {
                $aliases[] = 'custom knob';
            }
        }
        if (str_contains($family_lower, 'wand')) {
            $aliases[] = 'wand';
            if (str_contains($family_lower, 'ssv')) {
                $aliases[] = 'ssv wand';
            }
            if (str_contains(strtolower($catalog_name), 'con')) {
                $aliases[] = 'con wand';
            }
        }
        return self::unique_text(array_map('strtolower', $aliases));
    }

    private static function unique_text(array $values): array {
        $result = [];
        $seen = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $key = strtolower(preg_replace('/\s+/', ' ', $value));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $value;
        }
        return $result;
    }

    private static function row_diagnostic_text(array $rows): string {
        if (empty($rows)) {
            return 'No labeled rows were detected.';
        }
        $parts = [];
        foreach ($rows as $key => $row) {
            $parts[] = str_replace('_', ' ', $key) . '=' . (int) $row;
        }
        return implode(', ', $parts) . '.';
    }

    private static function normalize_label(string $value): string {
        $value = strtolower(trim($value));
        $value = str_replace(['–', '—'], '-', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim((string) $value);
    }

    private static function cell_value(array $cells, string $ref) {
        return $cells[$ref]['value'] ?? '';
    }

    private static function xml_attr(string $attrs, string $name): string {
        $pattern = '/\b' . preg_quote($name, '/') . '="([^"]*)"/i';
        return preg_match($pattern, $attrs, $match) ? self::xml_text($match[1]) : '';
    }

    private static function xml_text(string $text): string {
        return html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function delete_tree(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                self::delete_tree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public static function import_family(string $family, array $posted, bool $update = false): array {
        $session = self::session();
        $columns = $session['families'][$family] ?? [];
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($columns as $column) {
            if (empty($posted[$column]['selected'])) {
                $summary['skipped']++;
                continue;
            }
            $base = $session['items'][$column] ?? null;
            if (!$base) {
                $summary['skipped']++;
                continue;
            }
            $row = array_merge($base, [
                'catalog_name' => sanitize_text_field($posted[$column]['catalog_name'] ?? $base['catalog_name']),
                'search_aliases' => sanitize_textarea_field($posted[$column]['search_aliases'] ?? $base['search_aliases']),
                'compensation_method' => sanitize_key($posted[$column]['compensation_method'] ?? $base['compensation_method']),
                'blower_pay' => (float) ($posted[$column]['blower_pay'] ?? $base['blower_pay']),
            ]);
            $result = Elev8_OS_Production_Catalog_Service::import_wizard_item($row, $update);
            if (is_wp_error($result)) {
                $summary['errors'][] = $row['catalog_name'] . ': ' . $result->get_error_message();
            } elseif ($result === 'updated') {
                $summary['updated']++;
            } elseif ($result === 'skipped') {
                $summary['skipped']++;
            } else {
                $summary['created']++;
            }
        }
        return $summary;
    }

    private static function method_from_code(?float $value): string {
        if ($value === 1.0) {
            return 'piecework';
        }
        if ($value === 3.0) {
            return 'hourly';
        }
        if ($value === 2.0 || $value === 4.0) {
            return 'either';
        }
        return 'piecework';
    }

    private static function number($value): ?float {
        if ($value === null || $value === '' || is_array($value)) {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        return (float) $value;
    }

    private static function split_ref(string $ref): array {
        preg_match('/^([A-Z]+)(\d+)$/', $ref, $match);
        return [$match[1] ?? 'A', (int) ($match[2] ?? 1)];
    }

    private static function col_num(string $col): int {
        $number = 0;
        foreach (str_split($col) as $char) {
            $number = $number * 26 + (ord($char) - 64);
        }
        return $number;
    }

    private static function num_col(int $number): string {
        $value = '';
        while ($number > 0) {
            $number--;
            $value = chr(65 + $number % 26) . $value;
            $number = intdiv($number, 26);
        }
        return $value;
    }
}
