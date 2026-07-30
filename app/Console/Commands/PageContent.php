<?php

namespace App\Console\Commands;

/**
 * Canonical page copy for pages:seo-refresh. One method per page group so
 * the content stays readable. All copy: no em dashes, one internal link per
 * URL, H2/H3 hierarchy under the template H1, real store facts (cartons of
 * 200 sticks, 1-hour delivery zones, cash or card on delivery, 18+).
 *
 * @return array<string, array{content:string, faqs?:array<int,array{q:string,a:string}>}>
 */
class PageContent
{
    public static function all(): array
    {
        return array_merge(
            self::cities(),
            self::marketing(),
            self::legal(),
        );
    }

    /** The 7 TEREA delivery city pages. */
    protected static function cities(): array
    {
        $cities = [
            'terea-delivery-dubai' => [
                'city' => 'Dubai', 'window' => 'within about 1 hour',
                'areas' => 'Dubai Marina, Downtown Dubai, Business Bay, JBR, Deira, Bur Dubai, Jumeirah, Al Barsha, Silicon Oasis and the surrounding communities',
                'fast' => true,
            ],
            'terea-delivery-sharjah' => [
                'city' => 'Sharjah', 'window' => 'within about 1 hour',
                'areas' => 'Al Majaz, Al Nahda, Al Khan, Al Taawun, Muwaileh, Al Qasimia and nearby districts',
                'fast' => true,
            ],
            'terea-delivery-ajman' => [
                'city' => 'Ajman', 'window' => 'within about 1 hour',
                'areas' => 'Al Nuaimiya, Al Rashidiya, Al Jurf, the Corniche and the neighbouring residential areas',
                'fast' => true,
            ],
            'terea-delivery-abu-dhabi' => [
                'city' => 'Abu Dhabi', 'window' => 'the same day or on the next scheduled run',
                'areas' => 'Khalifa City, Al Reem Island, the Corniche, Mussafah, Al Reef and the wider capital',
                'fast' => false,
            ],
            'terea-delivery-al-ain' => [
                'city' => 'Al Ain', 'window' => 'the same day or on the next scheduled run',
                'areas' => 'Al Jimi, Al Mutaredh, the Town Centre, Al Muwaiji and the surrounding neighbourhoods',
                'fast' => false,
            ],
            'terea-delivery-ras-al-khaimah' => [
                'city' => 'Ras Al Khaimah', 'window' => 'the same day or on the next scheduled run',
                'areas' => 'Al Nakheel, Al Hamra, the Corniche, Al Dhait and nearby communities',
                'fast' => false,
            ],
            'terea-delivery-umm-al-quwain' => [
                'city' => 'Umm Al Quwain', 'window' => 'the same day or on the next scheduled run',
                'areas' => 'Al Salamah, the Old Town, King Faisal Road and the surrounding areas',
                'fast' => false,
            ],
        ];

        $pages = [];
        foreach ($cities as $slug => $c) {
            $pages[$slug] = self::cityPage($c);
        }

        return $pages;
    }

    protected static function cityPage(array $c): array
    {
        $city = $c['city'];
        $window = $c['window'];
        $fastLine = $c['fast']
            ? "{$city} sits inside our fastest delivery zone, so most orders arrive {$window} of confirmation."
            : "We deliver to {$city} {$window}, so you can plan your order around a clear, honest time window.";

        $content = <<<HTML
<h2>Genuine IQOS TEREA delivered in {$city}</h2>
<p>Terea Hub delivers authentic, factory sealed IQOS TEREA to {$city} every day. {$fastLine} Every order is genuine stock for the IQOS ILUMA range, sold by the full carton, and you pay only when it reaches your door.</p>

<h2>{$city} delivery times and coverage</h2>
<p>We cover {$c['areas']}. If you are unsure whether your address is in range, send it to us before you order and we will confirm the exact window.</p>
<table>
<thead><tr><th>Detail</th><th>What to expect</th></tr></thead>
<tbody>
<tr><td>Delivery window</td><td>{$window}</td></tr>
<tr><td>Sold as</td><td>Full cartons only (1 carton is 10 packs, 200 sticks)</td></tr>
<tr><td>Payment</td><td>Cash or card on delivery</td></tr>
<tr><td>Free delivery</td><td>On orders over 300 AED</td></tr>
</tbody>
</table>

<h2>Why customers in {$city} order from us</h2>
<ul>
<li>Only original, factory sealed TEREA for IQOS ILUMA, never loose or repacked stock.</li>
<li>A clear delivery window for {$city} instead of a vague "sometime today".</li>
<li>Cash or card when the order arrives, so you check it before you pay.</li>
<li>UAE market flavors and imported Japan editions from one trusted source.</li>
</ul>

<h2>How to order TEREA in {$city}</h2>
<ol>
<li>Browse the range and add the cartons you want from the <a href="/shop">TEREA shop</a>.</li>
<li>Enter your {$city} address and pick cash or card on delivery.</li>
<li>Wait for our confirmation with your delivery window.</li>
<li>Check the seal and the date code on arrival, then pay the driver.</li>
</ol>

<h2>Authenticity you can check on the doorstep</h2>
<p>Counterfeit sticks are common online, so we make authenticity easy to verify. Each carton arrives sealed with an intact factory wrap and a readable date code. If anything looks wrong when the driver hands it over, you do not have to accept or pay for it. You can read more in our <a href="/shipping-policy">delivery policy</a> and our <a href="/refund-policy">returns policy</a>.</p>

<h2>UAE and Japan edition TEREA</h2>
<p>We carry the UAE market flavors for everyday orders and a rotating selection of Japan edition cartons for something different. Explore the full lineup on the <a href="/category/terea-uae">TEREA UAE range</a> or the <a href="/category/terea-japan">Japan edition range</a>, then order to {$city} in a couple of taps.</p>
HTML;

        $faqs = [
            ['q' => "How fast is TEREA delivery in {$city}?", 'a' => "Most {$city} orders arrive {$window} of confirmation. We share your delivery window when we confirm the order so you are never left guessing."],
            ['q' => 'Do you sell single packs or only cartons?', 'a' => 'We sell full cartons only. One carton is 10 packs, which is 200 sticks. Buying by the carton works out cheaper per stick than single packs and means fewer reorders.'],
            ['q' => "Can I pay cash on delivery in {$city}?", 'a' => "Yes. You can pay cash or card when the order reaches your {$city} address, so you can inspect the sealed carton before you pay."],
            ['q' => 'Is the TEREA genuine?', 'a' => 'Yes. We sell only original, factory sealed IQOS TEREA for the ILUMA range. Check the seal and the printed date code when the driver arrives, and decline the order if anything looks off.'],
            ['q' => 'Is there a delivery fee?', 'a' => "Delivery to {$city} is free on orders over 300 AED. Smaller orders carry a standard delivery charge that is shown clearly at checkout before you confirm."],
        ];

        return ['content' => $content, 'faqs' => $faqs];
    }

    /** About, Contact, FAQ. */
    protected static function marketing(): array
    {
        return [
            'about-us' => ['content' => self::about(), 'faqs' => self::aboutFaqs()],
            'contact-us' => ['content' => self::contact()],
            'faq' => ['content' => self::faqIntro(), 'faqs' => self::faqList()],
        ];
    }

    protected static function about(): string
    {
        return <<<HTML
<h2>Who we are</h2>
<p>Terea Hub is a UAE based store dedicated to one thing done well: getting genuine IQOS TEREA to adult IQOS users quickly, honestly and at a fair price. We are not a general marketplace. We focus on the TEREA range for the IQOS ILUMA devices, which means we know the flavors, the editions and the questions buyers actually ask.</p>

<h2>What we sell</h2>
<p>We stock the UAE market TEREA flavors alongside a selection of imported Japan edition cartons. Everything is sold as a full carton (10 packs, 200 sticks) and is original, factory sealed stock. You can browse the whole range in the <a href="/shop">TEREA shop</a> or jump straight to the <a href="/category/terea-uae">UAE flavors</a>.</p>

<h2>How we work</h2>
<ul>
<li><strong>Genuine only.</strong> We source sealed cartons and never sell loose, opened or repacked sticks.</li>
<li><strong>Fast, honest delivery.</strong> Dubai, Sharjah and Ajman are usually within about an hour. The rest of the UAE is same day or on the next scheduled run.</li>
<li><strong>Pay on delivery.</strong> Cash or card when the order arrives, so you inspect the seal before you pay.</li>
<li><strong>Clear pricing.</strong> Carton pricing is shown up front, with free delivery over 300 AED.</li>
</ul>

<h2>Why buyers trust us</h2>
<p>Counterfeit and grey market sticks are a real problem, especially online. We built Terea Hub around authenticity you can verify on your own doorstep: an intact factory seal and a readable date code on every carton. If anything looks wrong, you are never obliged to accept or pay for the order. Our <a href="/shipping-policy">delivery policy</a> and <a href="/refund-policy">returns policy</a> spell out exactly how that works.</p>

<h2>Responsible use</h2>
<p>TEREA is a tobacco product intended for existing adult smokers aged 18 and over. It contains nicotine, which is addictive. We sell for a smoke free experience with compatible IQOS ILUMA devices only, and we make no health claims. If you do not already use nicotine, this product is not for you.</p>

<h2>Get in touch</h2>
<p>Questions about a flavor, an edition or your delivery window are welcome. Reach us through the <a href="/contact-us">contact page</a> and a real person will help.</p>
HTML;
    }

    protected static function aboutFaqs(): array
    {
        return [
            ['q' => 'Are you an official IQOS store?', 'a' => 'We are an independent UAE retailer specialising in genuine IQOS TEREA for the ILUMA range. We sell original, factory sealed stock and make authenticity easy to verify on delivery.'],
            ['q' => 'Which areas do you deliver to?', 'a' => 'We deliver across the UAE. Dubai, Sharjah and Ajman are usually within about an hour, and the rest of the Emirates are same day or on the next scheduled run.'],
            ['q' => 'Do you only sell TEREA?', 'a' => 'Our focus is the TEREA range for IQOS ILUMA, in both UAE market flavors and imported Japan editions, always by the full carton.'],
        ];
    }

    protected static function contact(): string
    {
        return <<<HTML
<h2>Talk to a real person</h2>
<p>Whether you are choosing between flavors, checking a delivery window, or following up on an order, we are happy to help. Terea Hub is run by a small team that knows the TEREA range well, so you get a straight answer rather than a script.</p>

<h2>How to reach us</h2>
<ul>
<li><strong>Order help:</strong> message us with your order details and city and we will confirm your delivery window.</li>
<li><strong>Flavor advice:</strong> tell us what you usually smoke or vape and we will point you to the closest TEREA match.</li>
<li><strong>Wholesale and bulk:</strong> ask about carton pricing for larger or repeat orders.</li>
</ul>

<h2>Before you message</h2>
<p>A few quick facts answer most questions on their own. We sell full cartons only (10 packs, 200 sticks). You pay cash or card on delivery. Delivery is free over 300 AED. If your question is about buying in your city, the <a href="/terea-delivery-dubai">city delivery pages</a> cover times and coverage in detail, and the <a href="/faq">FAQ</a> answers the rest.</p>

<h2>Responsible use</h2>
<p>Our products are for adult IQOS users aged 18 and over and contain nicotine. Please do not contact us to buy on behalf of a minor.</p>
HTML;
    }

    protected static function faqIntro(): string
    {
        return <<<HTML
<h2>Frequently asked questions</h2>
<p>Everything adult IQOS users in the UAE ask us most, in one place: genuine stock, cartons and pack sizes, delivery times, payment, and returns. If your question is not here, the <a href="/contact-us">contact page</a> reaches a real person.</p>
HTML;
    }

    protected static function faqList(): array
    {
        return [
            ['q' => 'Is your TEREA genuine?', 'a' => 'Yes. We sell only original, factory sealed IQOS TEREA for the ILUMA range. Every carton arrives with an intact seal and a readable date code, which you can check before you pay.'],
            ['q' => 'Do you sell single packs?', 'a' => 'No. We sell full cartons only. One carton is 10 packs, which is 200 sticks. Buying by the carton is cheaper per stick and means fewer reorders.'],
            ['q' => 'How fast is delivery?', 'a' => 'Dubai, Sharjah and Ajman are usually within about an hour of confirmation. The rest of the UAE is same day or on the next scheduled run. We share your window when we confirm the order.'],
            ['q' => 'How do I pay?', 'a' => 'Cash or card on delivery. You inspect the sealed carton when the driver arrives and pay only if everything is in order.'],
            ['q' => 'Is delivery free?', 'a' => 'Delivery is free on orders over 300 AED. Smaller orders carry a standard charge that is shown at checkout before you confirm.'],
            ['q' => 'What is the difference between UAE and Japan edition TEREA?', 'a' => 'Both are genuine TEREA for IQOS ILUMA. UAE editions are the flavors officially sold here. Japan editions are exclusive flavors from the Japanese market, imported by the carton.'],
            ['q' => 'What if my order looks wrong on arrival?', 'a' => 'If the seal is broken, the carton is damaged, or the order is incorrect, do not accept or pay for it. Our returns policy explains how we put it right.'],
            ['q' => 'Can anyone buy?', 'a' => 'No. TEREA is a tobacco product that contains nicotine and is intended for existing adult smokers aged 18 and over. We do not sell to minors.'],
        ];
    }

    /** YMYL legal pages: privacy, terms, refund, shipping. */
    protected static function legal(): array
    {
        return [
            'privacy-policy' => ['content' => self::privacy()],
            'terms-and-conditions' => ['content' => self::terms()],
            'refund-policy' => ['content' => self::refund()],
            'shipping-policy' => ['content' => self::shipping()],
        ];
    }

    protected static function privacy(): string
    {
        return <<<HTML
<p>This Privacy Policy explains what information Terea Hub collects when you use our website and place an order, how we use it, and the choices you have. By using this site you agree to the practices described here.</p>

<h2>Information we collect</h2>
<ul>
<li><strong>Order details</strong> you provide: name, delivery address, phone number, email, and the items you order.</li>
<li><strong>Payment context:</strong> because we use cash or card on delivery, we do not store card numbers on this site.</li>
<li><strong>Usage data:</strong> pages viewed, searches made and general device information, collected to keep the site working and to improve it.</li>
</ul>

<h2>How we use your information</h2>
<ul>
<li>To confirm, prepare and deliver your order and to share your delivery window.</li>
<li>To respond to your questions and provide customer support.</li>
<li>To detect and prevent fraud, abuse and misuse of the service.</li>
<li>To improve our range, our delivery and the website experience.</li>
</ul>

<h2>How we collect it</h2>
<p>We collect information in three ways: directly from you when you place an order or contact us, automatically as you browse (through cookies and standard server logs), and from the delivery partners who confirm that an order was completed. We only collect what we need to run the service.</p>

<h2>Cookies and analytics</h2>
<p>We use cookies and similar technologies to remember your cart, keep the site secure, measure performance and understand how the site is used so we can improve it. Essential cookies keep core features working. Analytics cookies are aggregated and help us see which pages and products are useful. You can control cookies through your browser settings, though blocking some may affect how parts of the site work.</p>

<h2>Sharing your information</h2>
<p>We do not sell your personal information. We share it only with the delivery drivers and service providers who help us fulfil your order, and only as far as they need it to do that work. These providers are expected to protect your data and use it solely for the service they provide to us. We may also disclose information where the law requires it, or to protect our rights and prevent fraud.</p>

<h2>How we protect your information</h2>
<p>We apply reasonable technical and organisational measures to protect your information against loss, misuse and unauthorised access. Because we deliver with cash or card on delivery, we do not store payment card numbers on this website, which removes a common risk entirely. No method of transmission over the internet is completely secure, so we cannot guarantee absolute security, but we work to keep your data safe.</p>

<h2>Data retention</h2>
<p>We keep order information for as long as needed to provide the service, meet our legal, tax and accounting obligations, and resolve any disputes. When information is no longer needed for these purposes, we remove or anonymise it.</p>

<h2>Your choices and rights</h2>
<p>You can ask us what personal information we hold about you, ask us to correct anything that is wrong, or ask us to delete information where we are not required to keep it. You can also ask us to stop sending you marketing messages at any time. To make a request, use the <a href="/contact-us">contact page</a>, and we will respond within a reasonable time.</p>

<h2>Marketing communications</h2>
<p>If you agree to receive updates from us, we may send occasional messages about products and offers. Every message includes a way to opt out, and opting out of marketing does not affect the messages we send to manage your orders.</p>

<h2>Links to other websites</h2>
<p>Our site may link to other websites that we do not control. This policy applies only to Terea Hub, so we encourage you to read the privacy policy of any other site you visit.</p>

<h2>Age restriction</h2>
<p>This site and our products are intended for adults aged 18 and over. We do not knowingly collect information from anyone under 18. If you believe a minor has provided us with personal information, contact us and we will remove it.</p>

<h2>Changes to this policy</h2>
<p>We may update this policy from time to time as our service or the law changes. The date at the top of this page shows when it was last revised, and significant changes will be reflected here. Continued use of the site after an update means you accept the revised policy.</p>

<h2>Contact us about privacy</h2>
<p>If you have a question or a complaint about how we handle your information, please reach us through the <a href="/contact-us">contact page</a> and we will do our best to resolve it.</p>
HTML;
    }

    protected static function terms(): string
    {
        return <<<HTML
<p>These Terms and Conditions govern your use of the Terea Hub website and your purchase of products from us. By placing an order you agree to these terms. Please read them carefully, together with our <a href="/privacy-policy">privacy policy</a>, which forms part of your agreement with us.</p>

<h2>Who we are</h2>
<p>Terea Hub is a UAE based retailer of genuine IQOS TEREA for the IQOS ILUMA range. In these terms, "we", "us" and "our" mean Terea Hub, and "you" means the person placing an order or using the site.</p>

<h2>Eligibility</h2>
<p>Our products are tobacco products that contain nicotine and are intended for existing adult smokers aged 18 and over. By ordering, you confirm that you are at least 18 years old and are buying for your own use, not on behalf of a minor.</p>

<h2>Products and authenticity</h2>
<p>We sell genuine, factory sealed IQOS TEREA for the IQOS ILUMA range, by the full carton (10 packs, 200 sticks). Product images and descriptions are provided in good faith. Flavors, editions and availability can change, and we may update or remove items without notice.</p>

<h2>Orders and acceptance</h2>
<p>Submitting an order is an offer to buy. Your order is accepted when we confirm it, and a contract is formed at that point. We may decline or cancel an order if the item is unavailable, if we cannot verify the delivery address or the recipient's age, if a price was clearly wrong, or if we suspect fraud or a breach of these terms. If we cancel an accepted order, you will not be charged for it.</p>

<h2>Cancellations and changes</h2>
<p>If you need to change or cancel an order, contact us as soon as possible through the <a href="/contact-us">contact page</a>. We can usually help before the order is dispatched. Once an order is out for delivery, the easiest option is to inspect it at the door and decline it if needed, as described in our returns policy.</p>

<h2>Pricing and payment</h2>
<p>Prices are shown in UAE dirhams and include applicable taxes unless stated otherwise. We take payment as cash or card on delivery. If a price is clearly wrong, we may cancel the affected order and let you know before delivery.</p>

<h2>Delivery</h2>
<p>Delivery times and coverage are described on our <a href="/shipping-policy">delivery policy</a>. Time windows are estimates given in good faith and can be affected by traffic, weather and demand.</p>

<h2>Returns</h2>
<p>Because these are sealed tobacco products, returns are limited to specific situations set out in our <a href="/refund-policy">returns policy</a>, such as a damaged, incorrect or unsealed carton on arrival.</p>

<h2>Acceptable use</h2>
<p>You agree not to misuse the site, attempt to disrupt it, or use it for any unlawful purpose. You may not resell our products in a way that misrepresents their origin or authenticity.</p>

<h2>Intellectual property</h2>
<p>The content on this site, including text, images and branding created by us, is our property or used with permission and may not be copied without our consent. Product brand names and marks belong to their respective owners.</p>

<h2>Limitation of liability</h2>
<p>We provide the site and products with reasonable care. To the extent permitted by law, we are not liable for indirect or consequential losses. Nothing in these terms limits any rights you have that cannot be excluded under UAE law.</p>

<h2>Governing law</h2>
<p>These terms are governed by the laws of the United Arab Emirates, and any dispute will be subject to the jurisdiction of the UAE courts.</p>

<h2>Contact</h2>
<p>Questions about these terms can be sent through the <a href="/contact-us">contact page</a>.</p>
HTML;
    }

    protected static function refund(): string
    {
        return <<<HTML
<p>This Returns and Refund Policy explains when you can return an order and how refunds work. Because we sell sealed tobacco products, returns are limited by law and by health and safety rules, so please check your order carefully on delivery.</p>

<h2>Check your order before you pay</h2>
<p>We deliver with cash or card on delivery specifically so you can inspect your order first. When the driver arrives, check that the carton is sealed, undamaged and correct, and that the date code is readable. If anything is wrong, you can decline the order there and then without paying.</p>

<h2>When we accept a return</h2>
<ul>
<li>The carton arrived <strong>damaged</strong> or with a broken factory seal.</li>
<li>You received the <strong>wrong item</strong> or the wrong quantity.</li>
<li>The order does not match what was confirmed.</li>
</ul>

<h2>When we cannot accept a return</h2>
<ul>
<li>The carton has been opened or the seal has been broken after delivery.</li>
<li>You changed your mind after accepting and paying for a sealed product.</li>
<li>The request is made outside a reasonable time after delivery.</li>
</ul>

<h2>How to request a return</h2>
<p>Contact us through the <a href="/contact-us">contact page</a> with your order details and, where possible, a photo of the issue. The faster you tell us, ideally on the day of delivery, the faster we can put it right.</p>

<h2>Refunds and replacements</h2>
<p>For an accepted return we will offer a replacement carton or a refund of the amount you paid for the affected item. Because most orders are paid on delivery, refunds are usually handled directly and promptly once the issue is confirmed. Where a card payment was taken, any refund is returned to the original payment method.</p>

<h2>How long refunds take</h2>
<p>Once we have confirmed an accepted return, we aim to arrange the replacement or refund as quickly as possible, usually within a few working days. If a refund goes back to a card, the time it takes to appear depends on your bank.</p>

<h2>Damaged or incorrect on arrival</h2>
<p>The simplest outcome is to refuse a damaged or incorrect order at the door, so you never pay for it. Full details of how delivery works are on our <a href="/shipping-policy">delivery policy</a>.</p>

<h2>Cancelled or undelivered orders</h2>
<p>If we cancel an order that you have already paid for, or an order cannot be delivered for a reason on our side, you receive a full refund of the amount paid, including any delivery charge.</p>

<h2>Your statutory rights</h2>
<p>This policy sits alongside your rights under UAE consumer protection law and does not remove any right that cannot be excluded. If you are unsure how it applies to your order, contact us and we will explain it clearly.</p>
HTML;
    }

    protected static function shipping(): string
    {
        return <<<HTML
<p>This Delivery Policy explains where we deliver, how long it takes, what it costs and how payment works. Our goal is a clear, honest delivery window rather than a vague promise.</p>

<h2>Where we deliver</h2>
<p>We deliver genuine IQOS TEREA across the United Arab Emirates. Each of our city pages, starting with <a href="/terea-delivery-dubai">TEREA delivery in Dubai</a>, lists the areas we cover and the local time window in detail.</p>

<h2>Delivery times</h2>
<table>
<thead><tr><th>Area</th><th>Typical window</th></tr></thead>
<tbody>
<tr><td>Dubai, Sharjah, Ajman</td><td>Within about 1 hour of confirmation</td></tr>
<tr><td>Abu Dhabi, Al Ain, Ras Al Khaimah, Umm Al Quwain</td><td>Same day or on the next scheduled run</td></tr>
</tbody>
</table>
<p>Windows are estimates given in good faith. Traffic, weather and demand can affect timing, and we will tell you if anything changes.</p>

<h2>Delivery charges</h2>
<p>Delivery is free on orders over 300 AED. Orders below that carry a standard delivery charge, which is shown clearly at checkout before you confirm. There are no hidden fees.</p>

<h2>Payment on delivery</h2>
<p>We deliver with cash or card on delivery. You inspect your sealed carton when the driver arrives and pay only if everything is correct. This is also how we make authenticity easy to verify.</p>

<h2>How your order is packed</h2>
<p>Every order is a full carton (10 packs, 200 sticks) of original, factory sealed TEREA. Cartons are handled carefully so they reach you sealed, with a readable date code, ready for you to check before you pay.</p>

<h2>Getting your address right</h2>
<p>Fast delivery depends on a complete address. Please include your area, building and any landmark that helps the driver find you, and keep your phone reachable so we can confirm your delivery window. If we cannot reach you or locate the address, delivery may be delayed.</p>

<h2>Order confirmation and updates</h2>
<p>After you place an order we confirm it along with your delivery window. If anything changes, for example due to traffic, weather or high demand, we will let you know rather than leave you waiting.</p>

<h2>Missed or failed delivery</h2>
<p>If the driver arrives and no one is available to receive the order, we will contact you to arrange a convenient time. Because payment is taken on delivery, a missed delivery does not cost you anything up front.</p>

<h2>Problems with a delivery</h2>
<p>If your order is late, damaged or incorrect, contact us through the <a href="/contact-us">contact page</a>. If it arrives damaged or wrong, you can refuse it at the door, and our <a href="/refund-policy">returns policy</a> explains what happens next.</p>

<h2>Age verification</h2>
<p>Our products are for adults aged 18 and over. Our drivers may ask to confirm the recipient is of legal age, and we reserve the right not to complete a delivery where age cannot be confirmed.</p>
HTML;
    }
}
