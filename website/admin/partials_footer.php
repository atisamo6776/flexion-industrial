        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php
$tinyKey = defined('TINYMCE_API_KEY') ? (string)TINYMCE_API_KEY : '';
if ($tinyKey !== ''): ?>
<script src="https://cdn.tiny.cloud/1/<?= e($tinyKey) ?>/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
<?php else: ?>
<script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
<?php endif; ?>
<script>
(function () {
  if (!window.tinymce) return;
  var opts = {
    selector: 'textarea.tinymce, textarea.tinymce-tr',
    plugins: 'lists link code',
    toolbar: 'undo redo | bold italic underline | fontsize forecolor | alignleft aligncenter alignright | bullist numlist | link | removeformat | code',
    menubar: false,
    height: 320,
    branding: false,
    promotion: false
  };
<?php if ($tinyKey === ''): ?>
  opts.base_url = 'https://cdn.jsdelivr.net/npm/tinymce@7';
  opts.suffix = '.min';
<?php endif; ?>
  tinymce.init(opts);
}());
</script>
</body>
</html>

