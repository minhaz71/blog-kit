<?php
    $waEnabled = (bool) setting('whatsapp.enabled', false);
    $waNumber = preg_replace('/\D+/', '', (string) setting('whatsapp.number', ''));
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($waEnabled && $waNumber !== ''): ?>
    <?php
        $waPosition = setting('whatsapp.position', 'left') === 'right' ? 'right' : 'left';
        $waDelay = max(0, (int) setting('whatsapp.delay_seconds', 3));
        $waGreeting = trim((string) setting('whatsapp.greeting', ''));
        $waMessage = trim((string) setting('whatsapp.message', ''));
        $waHref = 'https://wa.me/'.$waNumber.($waMessage !== '' ? '?text='.rawurlencode($waMessage) : '');
    ?>
    
    <a href="<?php echo e($waHref); ?>"
       class="wa-fab wa-<?php echo e($waPosition); ?>"
       target="_blank"
       rel="noopener nofollow"
       data-wa-delay="<?php echo e($waDelay); ?>"
       aria-label="<?php echo e($waGreeting !== '' ? $waGreeting : 'Chat on WhatsApp'); ?>">
        <span class="wa-fab-glow" aria-hidden="true"></span>
        <svg class="wa-fab-icon" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true">
            <path d="M16.003 3.2c-7.06 0-12.8 5.74-12.8 12.8 0 2.26.6 4.46 1.73 6.4L3.2 28.8l6.57-1.72a12.74 12.74 0 0 0 6.23 1.6h.01c7.05 0 12.79-5.74 12.79-12.8 0-3.42-1.33-6.63-3.75-9.05a12.7 12.7 0 0 0-9.05-3.63zm0 23.3h-.01a10.6 10.6 0 0 1-5.4-1.48l-.39-.23-4.02 1.05 1.07-3.92-.25-.4a10.56 10.56 0 0 1-1.62-5.63c0-5.86 4.77-10.63 10.63-10.63 2.84 0 5.5 1.11 7.51 3.12a10.56 10.56 0 0 1 3.11 7.52c0 5.86-4.77 10.63-10.63 10.63zm5.83-7.96c-.32-.16-1.89-.93-2.18-1.04-.29-.11-.5-.16-.72.16-.21.32-.82 1.04-1.01 1.25-.19.21-.37.24-.69.08-.32-.16-1.35-.5-2.57-1.58-.95-.85-1.59-1.9-1.78-2.22-.19-.32-.02-.49.14-.65.14-.14.32-.37.48-.56.16-.19.21-.32.32-.53.11-.21.05-.4-.03-.56-.08-.16-.72-1.73-.99-2.37-.26-.62-.52-.54-.72-.55l-.61-.01c-.21 0-.56.08-.85.4-.29.32-1.11 1.09-1.11 2.66 0 1.57 1.14 3.08 1.3 3.29.16.21 2.25 3.43 5.44 4.81.76.33 1.35.52 1.81.67.76.24 1.46.21 2 .13.61-.09 1.89-.77 2.16-1.52.27-.75.27-1.39.19-1.52-.08-.13-.29-.21-.61-.37z"/>
        </svg>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($waGreeting !== ''): ?>
            <span class="wa-fab-label"><?php echo e($waGreeting); ?></span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </a>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/partials/whatsapp-button.blade.php ENDPATH**/ ?>