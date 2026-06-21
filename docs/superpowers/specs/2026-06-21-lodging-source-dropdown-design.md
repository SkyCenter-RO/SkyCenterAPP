# Lodging Source Dropdown Design

## Goal

Reduce data-entry errors in the lodging administration forms by replacing the free-text reservation source with a controlled list and removing source fields that do not help operators configure properties or rooms.

## Approved Behavior

### Lodging reservations

The `source` field becomes a required dropdown with these operator-facing choices:

| Label | Stored value | Meaning |
| --- | --- | --- |
| Email | `gmail` | Reservation received or imported from email |
| Booking.com | `booking_com` | Reservation received from Booking.com |
| Airbnb | `airbnb` | Reservation received from Airbnb |
| Direct | `direct` | Reservation made directly by phone, WhatsApp, or message |

The form defaults to `direct`, because a reservation created manually by an operator is normally a direct reservation. The implementation must not show `manual` or legacy `booking` as choices for new reservations.

Existing records with `manual` or `booking` remain valid and editable. When editing one of these records, the form must preserve its current source unless the operator explicitly selects one of the new choices. Database compatibility for these values remains unchanged.

On an edit page, the dropdown adds the record's current legacy value as either `Manual (legacy)` or `Booking (legacy)`. This compatibility option is never present on a create page and cannot be selected for a new reservation.

### Lodging properties and rooms

The `source` field is removed from the create and edit forms for lodging properties and rooms. These records are internal configuration, so asking an operator to choose an origin creates confusion without adding useful business information.

The database columns and model attributes remain unchanged. New properties and rooms continue to receive the database default value `manual`, preserving existing uniqueness rules and integrations.

## Scope

This change affects only the Filament forms for lodging properties, rooms, and lodging reservations. It does not change the reservation webhook contract, database check constraints, existing data, list-table columns, sync-link channels, or other application modules.

## Alternatives Considered

- Keep free-text fields: rejected because it permits spelling variants and invalid values.
- Use one shared source list for properties, rooms, and reservations: rejected because reservation channels do not describe internal property and room configuration.
- Use a reservation-specific dropdown and remove irrelevant property and room fields: selected because it matches the operator's workflow while preserving the existing database structure.

## Validation and Compatibility

- The reservation dropdown is required and accepts only the four approved values for new operator-created records.
- Automated imports may continue writing values accepted by the database.
- Existing `manual` and `booking` records must not fail merely because those values are hidden from the new-record dropdown.
- An edit form for a legacy record must display and preserve its existing source until the operator deliberately replaces it.
- Property and room creation must still persist `source = manual` after the field is removed from their forms.

## Testing

Automated tests will verify that:

- The lodging reservation `source` component is a required `Select` with the four approved labels and stored values.
- The reservation source defaults to `direct`.
- `manual` and `booking` are not offered for new reservations.
- A legacy source is available only when it is already the current value of the edited record.
- Property and room forms no longer expose a `source` component.
- Property and room records created through their Filament create pages retain `source = manual`.
- Existing lodging panel page-rendering tests remain green.
