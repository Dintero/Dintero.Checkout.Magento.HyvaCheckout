# Changelog

v1.0.3
- Fixed the redirect flow handing out several payable Dintero sessions for the same merchant reference, which allowed multiple transactions to share one order number;
- The redirect flow now places the order before sending the customer to Dintero, matching the non Hyva redirect flow;
- Persisted the Dintero session id on the order payment;
- Applied the configured checkout language to the redirect url;

  Note: orders in the redirect flow are now created before the payment is completed and stay in
  status `pending` until Dintero confirms it. Abandoned checkouts therefore leave unpaid orders
  behind, the same way the non Hyva redirect flow already does.

v1.0.2
- Fixed validation before redirect to Dintero checkout;
- Implemented iframe auto reload for embedded checkout flow;

v1.0.1
- Adjusted compatibility of Embedded Express with Hyva Checkout and theme fallback;

