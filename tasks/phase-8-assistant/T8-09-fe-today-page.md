# T8-09 — Frontend: assistant Today page

**Phase:** 8 · **Area:** Frontend · **Size:** M · **Depends on:** T8-05, T8-06, T8-08
**Plan refs:** §7.2 · FR-I.4, FR-I.6

## Steps
- [ ] Make `DayView` reusable: props for the API functions and to hide "Start visit".
- [ ] Make `AddPaymentModal` take the payment mutation/API as a prop (the doctor keeps the current default).
- [ ] `pages/assistant/Today.jsx`: today's queue card (Arrived / No-show / Cancel) + "Waiting to pay" card (patient, total, paid, remaining, Record payment). Refresh both after actions.
- [ ] Empty, loading and error states via `QueryState`.

## Done when
- [ ] Vitest: the queue renders, waiting-to-pay renders, recording a payment calls the assistant API and refreshes the list.
