<?php

namespace App\Models\Concerns;

use App\Models\Faq;

trait HasFaqs
{
    public function faqs()
    {
        return $this->morphMany(Faq::class, 'faqable')->where('is_active', true)->orderBy('sort_order');
    }

    public function allFaqs()
    {
        return $this->morphMany(Faq::class, 'faqable')->orderBy('sort_order');
    }
}
