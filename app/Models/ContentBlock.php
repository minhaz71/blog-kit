<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentBlock extends Model
{
    public const TYPES = [
        'html' => 'HTML / rich text',
        'notice' => 'Info notice',
        'cta' => 'Call to action',
        'faq' => 'FAQ list',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** Rendered HTML for this block, used by the shortcode parser. */
    public function render(): string
    {
        $data = $this->data ?? [];

        return match ($this->type) {
            'notice' => sprintf(
                '<div class="my-4 rounded-lg border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-900">%s</div>',
                $this->body,
            ),
            'cta' => sprintf(
                '<div class="my-6 rounded-2xl bg-gray-900 p-6 text-white sm:p-10"><h3 class="text-xl font-bold">%s</h3><div class="mt-2 text-gray-300">%s</div>%s</div>',
                e($this->name),
                $this->body,
                ! empty($data['button_url']) && ! empty($data['button_text'])
                    ? sprintf('<a href="%s" class="mt-4 inline-block rounded-full bg-white px-5 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-100">%s</a>', e($data['button_url']), e($data['button_text']))
                    : '',
            ),
            'faq' => (function () use ($data) {
                $items = $data['items'] ?? [];
                if (! is_array($items) || empty($items)) {
                    return '';
                }
                $html = '<dl class="my-6 divide-y divide-gray-200 rounded-xl border border-gray-200">';
                foreach ($items as $item) {
                    $q = e($item['question'] ?? '');
                    $a = e($item['answer'] ?? '');
                    $html .= "<details class=\"group px-4 py-3\"><summary class=\"cursor-pointer list-none font-medium\">{$q}</summary><dd class=\"mt-2 text-gray-600\">{$a}</dd></details>";
                }

                return $html.'</dl>';
            })(),
            default => $this->body,
        };
    }
}
