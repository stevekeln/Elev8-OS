# Native Waitlist — Initial Specification

## Problem

Amelia does not currently provide the waitlist experience Elev8 Arts needs. A fake booking service labeled as a waitlist can trigger confusing booking and post-class emails.

## Goal

Create an Elev8 OS waitlist that does not book the customer into Amelia until a real seat is offered and accepted.

## Customer workflow

1. Class is full or unavailable.
2. Customer clicks **Join Waitlist**.
3. Customer enters name, email, phone, and consent.
4. Elev8 OS confirms their waitlist entry.
5. Customer receives a confirmation message that clearly says this is not a booking.
6. If a seat opens, Elev8 OS sends a time-limited offer.
7. Customer accepts or declines.
8. On acceptance, customer is sent into the real booking/payment flow.
9. If expired or declined, the next person is offered the seat.

## Notify-me workflow

When no class date exists, a customer can request notification for a future class.

This records demand without creating a fake booking.

## Admin workflow

Admin can see:

- Class or service
- Date
- Capacity
- Booked count
- Waitlist count
- Waitlist order
- Customer contact
- Joined date
- Offer status
- Expiration
- Notes
- Conversion to booking

## Intelligence

Future recommendations:

- Open another class
- Estimated additional revenue
- Estimated profit
- Best time or day based on prior demand
- Similar classes available now

## Required safeguards

- Consent and privacy notice
- Duplicate-entry handling
- Unsubscribe option
- Rate limiting
- Clear distinction between waitlist and booking
- Audit history
