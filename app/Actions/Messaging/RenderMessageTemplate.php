<?php

namespace App\Actions\Messaging;

use App\Models\MessageTemplate;

class RenderMessageTemplate
{
    /**
     * @param  array<string, string>  $placeholders
     * @return array{channel: string, text: string}|null
     */
    public function handle(string $service, string $templateKey, array $placeholders, string $locale = 'ro'): ?array
    {
        $template = MessageTemplate::query()
            ->where('service', $service)
            ->where('template_key', $templateKey)
            ->where('locale', $locale)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return null;
        }

        $text = strtr($template->body, $this->wrapPlaceholders($placeholders));
        $text = preg_replace('/\{\{[^}]*\}\}/', '', $text);

        return ['channel' => $template->channel, 'text' => $text];
    }

    /**
     * @param  array<string, string>  $placeholders
     * @return array<string, string>
     */
    private function wrapPlaceholders(array $placeholders): array
    {
        $wrapped = [];

        foreach ($placeholders as $key => $value) {
            $wrapped['{{'.$key.'}}'] = $value;
        }

        return $wrapped;
    }
}
