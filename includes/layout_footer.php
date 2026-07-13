<?php $isLogin = ($pageTitle ?? '') === 'Login'; ?>

<?php if (!$isLogin): ?>
    </div><!-- .content-area -->
</div><!-- .main-content -->
</div><!-- .wrapper -->
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<?php if (!empty($extraJs)) echo $extraJs; ?>
</body>
</html>
