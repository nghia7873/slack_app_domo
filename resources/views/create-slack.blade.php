<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>

    <title>Laravel</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">

</head>
<body class="antialiased">
<div class="container">
    <h2>Edit slack account</h2>
    <form action="{{ route('post-create-slack') }}" method="POST">
        @csrf
        <div class="form-group">
            <label for="token">Bot Token Slack:</label>
            <input type="text" class="form-control" id="token" name="token">
        </div>
        <div class="form-group">
            <label for="webhook_domo">Webhook domo json connector:</label>
            <input type="text" class="form-control" id="webhook_domo" name="webhook_domo">
        </div>
        <button type="submit" class="btn btn-default">Create</button>
        <a href="{{ route('list-slack') }}">Back</a>
    </form>
</div>
</body>
</html>
