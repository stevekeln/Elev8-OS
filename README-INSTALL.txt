Elev8 OS — Artist Dashboard feature package

Branch: feature/artist-dashboard

Files:
1. Replace:
   plugin/elev8-os/includes/Modules/class-elev8-os-dashboard-module.php
2. Replace:
   plugin/elev8-os/includes/class-elev8-os-loader.php
3. Add:
   plugin/elev8-os/assets/css/artist-dashboard.css

What this build does:
- Adds a logged-in Artist Dashboard.
- Matches a WordPress user to an Amelia artist by email.
- Displays the artist's name and real upcoming-class count.
- Shows a clear warning when no Amelia artist match exists.
- Adds placeholders for upcoming classes, statistics, earnings,
  quick actions, and notifications.
- Redirects linked non-admin artists to the dashboard after login.
- Leaves administrators' existing login destination unchanged.

Test before merging:
- Administrator can still access existing Elev8 OS pages.
- Linked artist lands on My Dashboard after login.
- Artist name is correct.
- Upcoming count matches future Amelia appointments.
- Unlinked user receives the connection warning without an error.
- Dashboard works on mobile.
