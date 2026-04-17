# Authorization

Workflow Automation uses a Laravel Gate to control access to the API and visual editor.

## How It Works

| Environment | Behavior |
| --- | --- |
| `local` | Always allowed — no gate check |
| `production`, `staging`, etc. | Must pass the `viewWorkflowAutomation` gate |

In **local** environments, all API routes and the visual editor are accessible without any authorization. In all other environments, access is denied unless the `viewWorkflowAutomation` gate returns `true`.

## Defining the Gate

Define the gate in your application's `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('viewWorkflowAutomation', function ($user) {
            return in_array($user->email, [
                'admin@example.com',
            ]);
        });
    }
}
```

The gate receives the authenticated user. If there is no authenticated user in a non-local environment, access is denied automatically.

## Examples

### Allow specific users

```php
Gate::define('viewWorkflowAutomation', function ($user) {
    return in_array($user->email, [
        'admin@example.com',
        'ops@example.com',
    ]);
});
```

### Allow by role (using Spatie permissions or similar)

```php
Gate::define('viewWorkflowAutomation', function ($user) {
    return $user->hasRole('admin');
});
```

### Allow all authenticated users

```php
Gate::define('viewWorkflowAutomation', function ($user) {
    return true;
});
```

## What Is Protected

The `Authorize` middleware is applied to:

- **API routes** — All CRUD and execution endpoints under the configured prefix (`/workflow-engine/*`)
- **Editor routes** — The visual workflow editor (`/workflow-editor`)

**Not protected** by this middleware:

- **Webhook endpoints** — These use their own authentication (bearer token, basic auth, header key) configured per-webhook

## Production Safety

::: danger
Make sure your `APP_ENV` is set to `production` in production environments. If `APP_ENV=local`, the workflow engine API and editor will be publicly accessible without any authorization.
:::

## Testing

In tests, define the gate to allow access:

```php
use Illuminate\Support\Facades\Gate;

protected function setUp(): void
{
    parent::setUp();

    Gate::define('viewWorkflowAutomation', fn ($user = null) => true);
}
```

Note the `$user = null` default — this allows the gate to pass even without an authenticated user, which is useful in tests that don't set up authentication.
