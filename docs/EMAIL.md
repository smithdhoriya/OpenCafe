# Email (Resend)

OpenCafe sends transactional email — account/email verification, password reset, and order
notifications — through [Resend](https://resend.com) via Laravel's built-in `resend` mail
transport (`resend/resend-php`, already a composer dependency).

## Local `.env` variables

Set these in your local `.env` (never in `.env.example`, which must stay secret-free):

```
MAIL_MAILER=resend
MAIL_FROM_ADDRESS="onboarding@resend.dev"
MAIL_FROM_NAME="${APP_NAME}"
RESEND_API_KEY=            # your Resend API key — fill this in yourself, never commit it
```

`config/services.php` reads the key from `RESEND_API_KEY` (not Resend's own default name,
`RESEND_KEY`). `.env.example` ships `RESEND_API_KEY=` blank and `MAIL_MAILER=log` as the
default, so a fresh checkout never needs Resend to boot and never risks shipping a real key.

## Sandbox sender and recipient restrictions ($0 plan)

Resend's free tier, without a verified custom domain, only allows:

- **From:** the shared sandbox address `onboarding@resend.dev` (what `MAIL_FROM_ADDRESS` is
  set to above).
- **To:** the email address of the Resend account that owns the API key — i.e. you can only
  send test emails to yourself, not to arbitrary customer addresses.

Resend also documents simulated addresses (`delivered@resend.dev`, `bounced@resend.dev`,
`complained@resend.dev`) that the live API accepts without actually delivering anything —
useful for a manual, deliberately-authorized smoke test. No automated test in this repo calls
the real API; see "Running tests" below.

## Enabling delivery to real/arbitrary customers

To send to any customer address (not just your own inbox), you need to, in the Resend
dashboard:

1. Add and verify a custom sending domain (DNS records: SPF/DKIM, and ideally DMARC).
2. Change `MAIL_FROM_ADDRESS` to an address on that verified domain (e.g.
   `orders@yourdomain.com`).

Until that's done, `MAIL_FROM_ADDRESS` must stay on `onboarding@resend.dev` and recipients
are restricted as described above. No code change is required when a domain is verified —
only the `.env` value changes.

## Which actions require a verified sender domain

- Order confirmation emails (`OrderConfirmation`) and order status emails
  (`OrderStatusChanged`) going to real customers.
- Any password-reset or email-verification link sent to a real customer's inbox rather than
  the Resend account owner's own address.

Nothing in the codebase itself needs a verified domain — it's purely a Resend account/DNS
configuration step, gated by the `MAIL_FROM_ADDRESS` env var.

## Queue requirements

`OrderConfirmation` and `OrderStatusChanged` are queued notifications (`ShouldQueue`), using
the project's existing `QUEUE_CONNECTION=database` setup — no new infrastructure. They are
dispatched only *after* the relevant database transaction/update has committed, so a queue or
mail failure can never roll back an already-placed order or status change.

For queued emails to actually leave the `jobs` table locally, a worker must be running:

```
php artisan queue:work
```

`composer run dev` already starts `php artisan queue:listen` alongside the app server, so
the normal local dev flow handles this automatically. If you only run `php artisan serve`
on its own, queued order emails will sit unprocessed in the `jobs` table until a worker runs
them — this is expected, not a bug, and avoids depending on a permanently-running worker
being a hard requirement for using the rest of the app.

The built-in auth notifications (email verification, password reset) are **not** queued —
they use Laravel's defaults — so those still send synchronously on the configured mailer
regardless of whether a queue worker is running.

## Running tests safely

The entire test suite forces `MAIL_MAILER=array` via `phpunit.xml`, which overrides whatever
`.env` says — so no test can ever reach the real Resend API or require a real key, even if
your local `.env` has `MAIL_MAILER=resend`. All mail/notification tests additionally use
`Illuminate\Support\Facades\Notification::fake()` (or build notification objects directly) so
no network call is made and no real email is ever sent.

```
php artisan test --compact
```

Relevant suites:
- `tests/Feature/Auth/*` — login, registration, email verification, password reset.
- `tests/Feature/Mail/*` — Resend transport config wiring, order notification content and
  dispatch rules.
- `tests/Feature/Customer/PlaceOrderTest.php`, `OrderCancellationTest.php`,
  `tests/Feature/Admin/AdminOrderStatusUpdateTest.php` — order lifecycle + notification
  side effects.

## Email verification

`App\Models\User` implements `MustVerifyEmail`. Registration dispatches Laravel's `Registered`
event, which `App\Providers\AppServiceProvider` wires to
`Illuminate\Auth\Listeners\SendEmailVerificationNotification` (this app has no
`EventServiceProvider`, so that default Laravel wiring doesn't happen automatically — it's
registered explicitly). Verification links are Laravel's standard signed, expiring URLs
(`config('auth.verification.expire')`, default 60 minutes) and can only mark the intended
account verified. The "resend verification email" action
(`resources/views/livewire/auth/verify-email.blade.php`) is rate-limited to 6 requests per
minute per user, matching the existing `throttle:6,1` already applied to the signed verify
route itself.

Only the `dashboard` route requires a verified email (`verified` middleware) — this was
already true before email verification was enabled, it just had no effect while
`MustVerifyEmail` was unimplemented. Guest browsing, cart, and checkout remain gated by plain
`auth` only, unchanged.

## Order emails

- **Order confirmation** (`App\Notifications\OrderConfirmation`): sent once, after
  `CreateOrderFromCart` successfully commits. Includes the order reference, each item with
  quantity/price, subtotal/discount/delivery charge/total, fulfillment or delivery details,
  and current order status. Never mentions payment status or any payment details.
- **Order status change** (`App\Notifications\OrderStatusChanged`): sent only for
  customer-meaningful transitions — Confirmed, Ready, Out for Delivery, Completed, Cancelled,
  Refunded (see `OrderStatus::notifiesCustomer()`). Pending is skipped (already covered by the
  confirmation email) and Preparing is skipped (kitchen-internal, not actionable for the
  customer).
- Both go through `App\Actions\Orders\NotifyOrderCustomer`, which resolves the real recipient
  (the account's user, or — for the guest-checkout data model that already exists on `Order`
  but has no UI path yet — the captured guest email) and silently skips with a logged warning
  if neither is available, so a missing address can never break order placement or an admin
  status update.
- Duplicate sends are prevented by the same conditional-update guards `UpdateOrderStatus` and
  `CancelOrder` already used before this change — a transition can only actually succeed once,
  so the notification can only fire once per real transition.

## Branding

The notification mail HTML/text templates are Laravel's own
(`resources/views/vendor/mail/...`, published via `php artisan vendor:publish --tag=laravel-mail`)
with `resources/views/vendor/mail/html/themes/default.css` retinted to OpenCafe's
cream/espresso/emerald palette. The header/footer already read `config('app.name')`, so with
`APP_NAME=OpenCafe` set, emails show plain-text "OpenCafe" branding — no logo image, no
external asset requests, no tracking pixels.
