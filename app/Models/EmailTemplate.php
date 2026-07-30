<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Replace {{placeholders}} in subject/heading/body with actual values.
     *
     * Escaping differs by destination:
     *  - subject: plain-text email header — values must NOT be HTML-escaped, or
     *    a name like O'Brien shows as "O&#039;Brien".
     *  - heading: rendered via Blade {{ }} which escapes at output, so values
     *    must be raw here (pre-escaping would double-escape).
     *  - body: rendered raw via {!! !!}, so placeholder values ARE HTML-escaped
     *    to keep customer-supplied data (e.g. their name) from injecting markup.
     */
    public function render(array $vars): array
    {
        $raw = fn (?string $text): string => preg_replace_callback(
            '/{{\s*([\w.]+)\s*}}/',
            fn ($m) => (string) data_get($vars, $m[1], ''),
            $text ?? '',
        );

        $escaped = fn (?string $text): string => preg_replace_callback(
            '/{{\s*([\w.]+)\s*}}/',
            fn ($m) => e((string) data_get($vars, $m[1], '')),
            $text ?? '',
        );

        return [
            'subject' => $raw($this->subject),
            'heading' => $raw($this->heading),
            'body' => $escaped($this->body),
        ];
    }
}
