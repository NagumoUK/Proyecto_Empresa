<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use NotificationChannels\WebPush\PushSubscription;

class StorePushSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:'.PushSubscription::ENDPOINT_MAX_LENGTH, 'url:https'],
            'public_key' => ['required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_-]+={0,2}\z/'],
            'auth_token' => ['required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_-]+={0,2}\z/'],
            'content_encoding' => ['required', 'in:aes128gcm'],
        ];
    }

    /**
     * Ensure push endpoints belong to a supported browser push service.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('endpoint')) {
                    return;
                }

                $host = parse_url($this->string('endpoint')->toString(), PHP_URL_HOST);

                if (! is_string($host) || ! $this->isSupportedPushService($host)) {
                    $validator->errors()->add(
                        'endpoint',
                        'El endpoint debe pertenecer a un servicio de notificaciones push compatible.',
                    );
                }
            },
        ];
    }

    private function isSupportedPushService(string $host): bool
    {
        $host = Str::lower($host);

        return in_array($host, [
            'fcm.googleapis.com',
            'push.services.mozilla.com',
            'updates.push.services.mozilla.com',
            'web.push.apple.com',
        ], true) || str_ends_with($host, '.notify.windows.com');
    }
}
