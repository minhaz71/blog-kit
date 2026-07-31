<?php
    use App\Support\StoreBranding;

    $b = StoreBranding::emailBranding();
    $brand = $b['brand'];
    $brandDark = $b['brand_dark'];
    $headerText = $b['header_text_color'] ?? '#ffffff';
    $host = parse_url($b['url'], PHP_URL_HOST) ?: $b['url'];

    // Rich order sections only render for customer ORDER emails. A CART
    // reminder (abandoned cart) is a different context: the shopper hasn't
    // ordered yet, so it shows their saved cart + a "Complete your order"
    // button — never an order tracker, invoice, or "View your order".
    $isCart = ($order['audience'] ?? null) === 'cart' && ! empty($order['items']);
    $isCustomerOrder = ($order['audience'] ?? null) === 'customer' && ! empty($order['items']);
    $isAdmin = ($order['audience'] ?? null) === 'admin';
    $cartUrl = $order['cart_url'] ?? $b['url'];
    $showTracker  = $isCustomerOrder && setting('emails.email_show_tracker', true);
    $showInvoice  = $isCustomerOrder && setting('emails.email_show_invoice_button', true) && ! empty($order['invoice_url']);
    $showRelated  = $isCustomerOrder && setting('emails.email_show_related', true) && ! empty($order['related']);
    $showPromo    = $isCustomerOrder && setting('emails.email_show_promo', true);

    $step = (int) ($order['step'] ?? 1);
    $steps = ['Order Confirmed', 'Shipped', 'Delivered'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?php echo e($heading); ?></title>
    
    <style>
        @media only screen and (max-width:600px) {
            /* Comfortable side gutters instead of the desktop 36px. */
            .em-pad { padding-left:20px !important; padding-right:20px !important; }
            .em-header { padding-left:20px !important; padding-right:20px !important; }
            /* Two-column blocks (addresses, related products) stack full-width. */
            .em-col { display:block !important; width:100% !important;
                      padding-left:0 !important; padding-right:0 !important;
                      padding-bottom:12px !important; box-sizing:border-box; }
            .em-col:last-child { padding-bottom:0 !important; }
            .em-h1 { font-size:21px !important; line-height:1.3 !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#f1f5f9; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; -webkit-font-smoothing:antialiased;">
    <span style="display:none; max-height:0; overflow:hidden; font-size:1px; color:#f1f5f9;"><?php echo e($heading); ?></span>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9; padding:28px 0;">
        <tr><td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%;">

                
                <tr>
                    <td class="em-header" style="background:<?php echo e($brand); ?>; background-image:linear-gradient(135deg,<?php echo e($brand); ?>,<?php echo e($brandDark); ?>); border-radius:14px 14px 0 0; padding:24px 36px;" align="center">
                        <a href="<?php echo e($b['url']); ?>" style="text-decoration:none;">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($b['logo_url']): ?>
                                <img src="<?php echo e($b['logo_url']); ?>" alt="<?php echo e($b['name']); ?>" height="40" style="height:40px; max-height:40px; display:inline-block; border:0;">
                            <?php else: ?>
                                <span style="color:<?php echo e($headerText); ?>; font-size:22px; font-weight:800; letter-spacing:-0.02em;"><?php echo e($b['name']); ?></span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </a>
                    </td>
                </tr>

                
                <tr>
                    <td class="em-pad" style="background:#ffffff; padding:34px 36px 8px;">
                        <h1 style="margin:0 0 14px; font-size:23px; line-height:1.3; color:#0f172a; font-weight:800;"><?php echo e($heading); ?></h1>
                        <div style="font-size:15px; line-height:1.7; color:#334155;"><?php echo $body; ?></div>
                    </td>
                </tr>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showTracker): ?>
                    <tr><td class="em-pad" style="background:#ffffff; padding:20px 36px 6px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $steps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <?php $n = $i + 1; $done = $n <= $step; ?>
                                    <td align="center" style="width:33.33%;">
                                        <div style="width:34px; height:34px; line-height:34px; margin:0 auto; border-radius:50%; font-size:15px; font-weight:700; color:#ffffff; background:<?php echo e($done ? $brand : '#cbd5e1'); ?>;">
                                            <?php echo e($done ? '✓' : $n); ?>

                                        </div>
                                        <div style="margin-top:8px; font-size:11px; font-weight:600; color:<?php echo e($done ? '#0f172a' : '#94a3b8'); ?>;"><?php echo e($label); ?></div>
                                    </td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </tr>
                        </table>
                    </td></tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isCart): ?>
                    <tr><td class="em-pad" style="background:#ffffff; padding:22px 36px 6px;" align="center">
                        <table role="presentation" cellpadding="0" cellspacing="0" style="display:inline-block;">
                            <tr><td style="border-radius:10px; background:<?php echo e($brand); ?>;">
                                <a href="<?php echo e($cartUrl); ?>" style="display:inline-block; padding:14px 30px; font-size:15px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:10px;">Complete your order &rarr;</a>
                            </td></tr>
                        </table>
                        <p style="margin:10px 0 0; font-size:12px; color:#94a3b8;">Your cart is saved — pick up right where you left off.</p>
                    </td></tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isCustomerOrder && (! empty($order['url']) || $showInvoice)): ?>
                    <tr><td class="em-pad" style="background:#ffffff; padding:22px 36px 6px;" align="center">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($order['url'])): ?>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="display:inline-block; margin:0 6px 10px;">
                                <tr><td style="border-radius:10px; background:<?php echo e($brand); ?>;">
                                    <a href="<?php echo e($order['url']); ?>" style="display:inline-block; padding:13px 26px; font-size:15px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:10px;">View Your Order &rarr;</a>
                                </td></tr>
                            </table>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showInvoice): ?>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="display:inline-block; margin:0 6px 10px;">
                                <tr><td style="border-radius:10px; background:#0f172a;">
                                    <a href="<?php echo e($order['invoice_url']); ?>" style="display:inline-block; padding:13px 26px; font-size:15px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:10px;">⬇ Download PDF Invoice</a>
                                </td></tr>
                            </table>
                            <p style="margin:2px 0 0; font-size:12px; color:#94a3b8;">Need your invoice PDF? Download it any time — no login required.</p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td></tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($order['items'])): ?>
                    <tr><td class="em-pad" style="background:#ffffff; padding:26px 36px 8px;">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isCart): ?>
                            <p style="margin:0 0 12px; font-size:13px; font-weight:700; color:#0f172a;">Your saved cart</p>
                        <?php elseif(! empty($order['number'])): ?>
                            <p style="margin:0 0 12px; font-size:13px; color:#64748b;">Order <strong style="color:#0f172a;">#<?php echo e($order['number']); ?></strong></p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $order['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr>
                                    <td width="56" style="padding:10px 12px 10px 0; vertical-align:top;">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($item['image'])): ?>
                                            <img src="<?php echo e($item['image']); ?>" alt="<?php echo e($item['name']); ?>" width="52" height="52" style="width:52px; height:52px; border-radius:8px; border:1px solid #e2e8f0; object-fit:cover;">
                                        <?php else: ?>
                                            <div style="width:52px; height:52px; border-radius:8px; background:#f1f5f9; border:1px solid #e2e8f0;"></div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <td style="padding:10px 0; vertical-align:top; border-bottom:1px solid #f1f5f9;">
                                        <div style="font-size:14px; font-weight:600; color:#0f172a;"><?php echo e($item['name']); ?></div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($item['options'])): ?><div style="font-size:12px; color:#64748b; margin-top:2px;"><?php echo e($item['options']); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <div style="font-size:12px; color:#94a3b8; margin-top:2px;">Qty: <?php echo e($item['qty']); ?></div>
                                    </td>
                                    <td align="right" style="padding:10px 0; vertical-align:top; border-bottom:1px solid #f1f5f9; font-size:14px; font-weight:600; color:#0f172a; white-space:nowrap;"><?php echo e($item['total']); ?></td>
                                </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </table>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px;">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isCart): ?>
                                
                                <tr><td align="right" style="padding:8px 0 0; font-size:16px; font-weight:800; color:#0f172a;">Cart total</td><td align="right" width="90" style="padding:8px 0 0; font-size:16px; font-weight:800; color:<?php echo e($brandDark); ?>;"><?php echo e($order['subtotal']); ?></td></tr>
                            <?php else: ?>
                                <tr><td align="right" style="padding:3px 0; font-size:14px; color:#64748b;">Subtotal</td><td align="right" width="90" style="padding:3px 0; font-size:14px; color:#0f172a;"><?php echo e($order['subtotal']); ?></td></tr>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($order['discount'])): ?><tr><td align="right" style="padding:3px 0; font-size:14px; color:#059669;">Discount</td><td align="right" style="padding:3px 0; font-size:14px; color:#059669;">−<?php echo e($order['discount']); ?></td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <tr><td align="right" style="padding:3px 0; font-size:14px; color:#64748b;">Shipping</td><td align="right" style="padding:3px 0; font-size:14px; color:#0f172a;"><?php echo e($order['shipping']); ?></td></tr>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($order['tax'])): ?><tr><td align="right" style="padding:3px 0; font-size:14px; color:#64748b;">Tax</td><td align="right" style="padding:3px 0; font-size:14px; color:#0f172a;"><?php echo e($order['tax']); ?></td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($order['payment_fee'])): ?><tr><td align="right" style="padding:3px 0; font-size:14px; color:#64748b;"><?php echo e($order['payment_fee_label']); ?></td><td align="right" style="padding:3px 0; font-size:14px; color:#0f172a;"><?php echo e($order['payment_fee']); ?></td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <tr><td align="right" style="padding:10px 0 0; font-size:16px; font-weight:800; color:#0f172a;">Total</td><td align="right" style="padding:10px 0 0; font-size:16px; font-weight:800; color:<?php echo e($brandDark); ?>;"><?php echo e($order['total']); ?></td></tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </table>
                    </td></tr>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isCustomerOrder && ! empty($order['shipping_address']['address_line_1'] ?? $order['shipping_address']['first_name'] ?? null)): ?>
                        <?php $sa = $order['shipping_address']; ?>
                        <tr><td class="em-pad" style="background:#ffffff; padding:18px 36px 6px;">
                            <div style="border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px;">
                                <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:#94a3b8; margin-bottom:4px;">Shipping to</div>
                                <div style="font-size:13px; line-height:1.6; color:#334155;">
                                    <strong style="color:#0f172a;"><?php echo e(trim(($sa['first_name'] ?? '').' '.($sa['last_name'] ?? ''))); ?></strong><br>
                                    <?php echo e($sa['address_line_1'] ?? ''); ?><?php echo e(! empty($sa['address_line_2']) ? ', '.$sa['address_line_2'] : ''); ?><br>
                                    <?php echo e(trim(($sa['city'] ?? '').' '.($sa['postal_code'] ?? ''))); ?><?php echo e(! empty($sa['country']) ? ', '.$sa['country'] : ''); ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($order['payment_label'])): ?><br><span style="color:#64748b;">Paid via <?php echo e($order['payment_label']); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </div>
                        </td></tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isAdmin): ?>
                    <?php $cust = $order['customer'] ?? []; $ba = $order['billing_address'] ?? []; $sa = $order['shipping_address'] ?? []; ?>
                    <tr><td class="em-pad" style="background:#ffffff; padding:18px 36px 6px;">
                        <div style="border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; margin-bottom:12px;">
                            <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:#94a3b8; margin-bottom:4px;">Customer</div>
                            <div style="font-size:14px; font-weight:700; color:#0f172a;"><?php echo e($cust['name'] ?? '—'); ?></div>
                            <div style="font-size:13px; color:#334155;"><?php echo e($cust['email'] ?? ''); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($cust['phone'])): ?> · <?php echo e($cust['phone']); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                            <div style="margin-top:8px; font-size:13px; color:#334155;">
                                <strong style="color:<?php echo e($brandDark); ?>;"><?php echo e($cust['orders_count'] ?? 1); ?></strong> order(s) placed
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($cust['lifetime_total'])): ?> · lifetime spend <strong style="color:#0f172a;"><?php echo e($cust['lifetime_total']); ?></strong><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                            <td class="em-col" width="50%" style="vertical-align:top; padding-right:6px;">
                                <div style="border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px;">
                                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:#94a3b8; margin-bottom:4px;">Billing address</div>
                                    <div style="font-size:13px; line-height:1.6; color:#334155;">
                                        <strong style="color:#0f172a;"><?php echo e(trim(($ba['first_name'] ?? '').' '.($ba['last_name'] ?? '')) ?: '—'); ?></strong><br>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($ba['address_line_1'])): ?><?php echo e($ba['address_line_1']); ?><?php echo e(! empty($ba['address_line_2']) ? ', '.$ba['address_line_2'] : ''); ?><br><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php echo e(trim(($ba['city'] ?? '').' '.($ba['postal_code'] ?? ''))); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($ba['country'])): ?>, <?php echo e($ba['country']); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($cust['phone'])): ?><br><a href="tel:<?php echo e($cust['phone']); ?>" style="color:<?php echo e($brandDark); ?>; text-decoration:none;"><?php echo e($cust['phone']); ?></a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($cust['email'])): ?><br><a href="mailto:<?php echo e($cust['email']); ?>" style="color:<?php echo e($brandDark); ?>; text-decoration:none;"><?php echo e($cust['email']); ?></a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="em-col" width="50%" style="vertical-align:top; padding-left:6px;">
                                <div style="border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px;">
                                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:#94a3b8; margin-bottom:4px;">Shipping address</div>
                                    <div style="font-size:13px; line-height:1.6; color:#334155;">
                                        <strong style="color:#0f172a;"><?php echo e(trim(($sa['first_name'] ?? '').' '.($sa['last_name'] ?? '')) ?: '—'); ?></strong><br>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($sa['address_line_1'])): ?><?php echo e($sa['address_line_1']); ?><?php echo e(! empty($sa['address_line_2']) ? ', '.$sa['address_line_2'] : ''); ?><br><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php echo e(trim(($sa['city'] ?? '').' '.($sa['postal_code'] ?? ''))); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($sa['country'])): ?>, <?php echo e($sa['country']); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr></table>
                    </td></tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($isCustomerOrder || $isCart) && ($b['email'] || $b['phone'])): ?>
                    <tr><td class="em-pad" style="background:#ffffff; padding:22px 36px 6px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($b['email']): ?>
                                    <td class="em-col" width="50%" style="padding:6px;">
                                        <div style="border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px;">
                                            <div style="font-size:13px; font-weight:700; color:#0f172a;">✉ Email us</div>
                                            <a href="mailto:<?php echo e($b['email']); ?>" style="font-size:12px; color:<?php echo e($brandDark); ?>; text-decoration:none;"><?php echo e($b['email']); ?></a>
                                        </div>
                                    </td>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($b['phone']): ?>
                                    <td class="em-col" width="50%" style="padding:6px;">
                                        <div style="border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px;">
                                            <div style="font-size:13px; font-weight:700; color:#0f172a;">☎ Call us</div>
                                            <a href="tel:<?php echo e(preg_replace('/[^0-9+]/', '', $b['phone'])); ?>" style="font-size:12px; color:<?php echo e($brandDark); ?>; text-decoration:none;"><?php echo e($b['phone']); ?></a>
                                        </div>
                                    </td>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tr>
                        </table>
                    </td></tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showPromo): ?>
                    <tr><td class="em-pad" style="background:#ffffff; padding:16px 36px 6px;">
                        <div style="background:#fff7ed; border-radius:12px; padding:20px 22px; text-align:center;">
                            <div style="font-size:18px; font-weight:800; color:#0f172a;"><?php echo e(setting('emails.promo_heading', 'Free delivery on your next order')); ?></div>
                            <p style="margin:6px 0 14px; font-size:13px; color:#64748b;"><?php echo e(setting('emails.promo_text', 'Come back soon — genuine IQOS TEREA, delivered fast across the UAE.')); ?></p>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="display:inline-block;">
                                <tr><td style="border-radius:10px; background:<?php echo e($brand); ?>;">
                                    <a href="<?php echo e($b['url']); ?>" style="display:inline-block; padding:11px 24px; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:10px;"><?php echo e(setting('emails.promo_cta', 'Shop Now')); ?></a>
                                </td></tr>
                            </table>
                        </div>
                    </td></tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showRelated): ?>
                    <tr><td class="em-pad" style="background:#ffffff; padding:22px 36px 8px;">
                        <div style="font-size:16px; font-weight:800; color:#0f172a; margin-bottom:14px;">You may also like</div>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $order['related']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <td width="33.33%" style="padding:0 6px; vertical-align:top;" align="center">
                                        <a href="<?php echo e($rp['url']); ?>" style="text-decoration:none; color:inherit;">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($rp['image'])): ?>
                                                <img src="<?php echo e($rp['image']); ?>" alt="<?php echo e($rp['name']); ?>" width="100%" style="width:100%; max-width:160px; border-radius:10px; border:1px solid #e2e8f0;">
                                            <?php else: ?>
                                                <div style="width:100%; max-width:160px; height:110px; border-radius:10px; background:#f1f5f9; border:1px solid #e2e8f0; margin:0 auto;"></div>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <div style="font-size:12px; font-weight:600; color:#0f172a; margin-top:8px; line-height:1.3;"><?php echo e(\Illuminate\Support\Str::limit($rp['name'], 40)); ?></div>
                                            <div style="font-size:12px; font-weight:700; color:<?php echo e($brandDark); ?>; margin-top:2px;"><?php echo e($rp['price']); ?></div>
                                        </a>
                                    </td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </tr>
                        </table>
                    </td></tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $isCustomerOrder && ! $isCart && $b['email']): ?>
                    <tr><td class="em-pad" style="background:#ffffff; padding:6px 36px 20px;">
                        <p style="margin:20px 0 0; padding-top:18px; border-top:1px solid #f1f5f9; font-size:13px; color:#64748b;">
                            Questions? Reach us at <a href="mailto:<?php echo e($b['email']); ?>" style="color:<?php echo e($brandDark); ?>; font-weight:600;"><?php echo e($b['email']); ?></a>.
                        </p>
                    </td></tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <tr><td class="em-pad" style="background:#ffffff; padding:10px 36px 30px;"></td></tr>

                
                <tr>
                    <td class="em-pad" style="background:#0f172a; border-radius:0 0 14px 14px; padding:26px 36px; text-align:center;">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($b['socials'])): ?>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="display:inline-block; margin-bottom:14px;"><tr>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $b['socials']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <td style="padding:0 5px;">
                                        <a href="<?php echo e($s['url']); ?>" style="display:inline-block; width:30px; height:30px; line-height:30px; border-radius:50%; background:#1e293b; color:#ffffff; font-size:13px; font-weight:700; text-decoration:none;" title="<?php echo e($s['label']); ?>"><?php echo e($s['initial']); ?></a>
                                    </td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </tr></table>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($b['footer_text'])): ?>
                            <div style="font-size:13px; line-height:1.6; color:#cbd5e1; margin-bottom:10px;"><?php echo e($b['footer_text']); ?></div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <div style="font-size:12px; line-height:1.7; color:#94a3b8;">
                            <strong style="color:#e2e8f0;"><?php echo e($b['name']); ?></strong><br>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $b['address_lines']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><?php echo e($line); ?><br><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($b['phone']): ?><?php echo e($b['phone']); ?> · <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><a href="<?php echo e($b['url']); ?>" style="color:#cbd5e1; text-decoration:none;"><?php echo e($host); ?></a>
                        </div>
                        <div style="font-size:11px; color:#64748b; margin-top:12px;">© <?php echo e(date('Y')); ?> <?php echo e($b['name']); ?>. All rights reserved.</div>
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/emails/templated.blade.php ENDPATH**/ ?>