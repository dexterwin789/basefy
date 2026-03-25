<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <title>Admin Panel</title>
</head>
<body class="bg-black text-white">
    <?php include 'partials/header.php'; ?>

    <div class="container mx-auto p-4">
        <?php echo $content; ?>
    </div>

    <?php include 'partials/footer.php'; ?>
</body>
</html>