<!DOCTYPE html>
<html class="<?= isDarkMode() ? 'dark' : 'light' ?>" lang="<?= e($language ?? getCurrentLanguage()) ?>">
<?php include VIEWS_PATH . '/partials/head.php'; ?>

<body class="bg-clean font-body text-gray-800 dark:text-gray-100 min-h-screen">
    <!-- National Flag Accent Bar -->
    <div class="flag-accent w-full"></div>

    <div id="app" class="relative flex min-h-screen w-full flex-col overflow-y-auto">
        
        <?php include VIEWS_PATH . '/partials/header.php'; ?>

        <!-- ===== MAIN CONTENT ===== -->
        <main class="flex-1 flex justify-center p-4 lg:p-6 lg:pb-10 overflow-y-auto">
            <div class="w-full max-w-[1400px] grid grid-cols-1 lg:grid-cols-12 gap-8">
                
                <?php 
                // Inclure le contenu de la page
                include VIEWS_PATH . "/{$view}.php"; 
                ?>
                
            </div>
        </main>
    </div>

    <?php include VIEWS_PATH . '/partials/modals.php'; ?>
    <?php include VIEWS_PATH . '/partials/scripts.php'; ?>
</body>

</html>

