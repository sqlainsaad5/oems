        </main>
        <footer class="app-footer">
            <span>&copy; <?= date('Y') ?> <?= e(APP_FULL_NAME) ?></span>
            <span>v<?= e(APP_VERSION) ?></span>
        </footer>
    </div>
</div>
<script src="<?= asset('js/app.js') ?>" defer></script>
<?php if (!empty($extraScripts)): ?>
    <?php foreach ((array)$extraScripts as $src): ?>
        <script src="<?= e($src) ?>" defer></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
