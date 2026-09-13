# BubbaHub Booking Form — Ninja Forms + GetPaid

Version: 4.3.4

## Flow

Venue → Class → Available Date → Multiple Tickets → Pay Now / Reserve Now.

The booking engine stores bookings, calculates availability, emails the customer/admin/venue owner, and optionally creates a GetPaid invoice. No OpenAI API is used.

## Ninja Forms

Create a Ninja Form with customer fields for first name, last name, email and phone.

Add two hidden fields with these exact field keys:

- `bh_booking_payload`
- `bh_booking_token`

Add an HTML field containing:

```text
[bubbahub_booking]
```

Then add the normal Ninja Forms submit button and optionally the Record Submission action.

## Settings

Go to **Settings → BubbaHub Bookings**.

The booking engine automatically detects `venue`, `venues`, `group` or `groups` as the venue post type. You can override this.

Configure the ACF field names for:

- Classes: default `booking_classes`
- Dates: default `booking_dates`
- Tickets: default `booking_tickets`

The ACF values can be repeaters, simple lists/text, or JSON.

Recommended class row fields: `name`, `key`, `price`.

Recommended date row fields: `date`, `capacity`, `available`.

Recommended ticket row fields: `name`, `price`, `max`.

## GetPaid

Create one generic GetPaid item called **BubbaHub Booking** and select it in the BubbaHub Bookings settings. The booking engine overrides that item's price with the calculated booking total and stores the booking ID in the invoice metadata.

A Pay Now booking holds capacity for the configured period until payment is completed. A successful GetPaid payment changes the booking to confirmed.

## Security

The server re-validates the selected venue, class, date, ticket quantities and availability before creating a booking. The browser's displayed total is never trusted for payment calculations.
