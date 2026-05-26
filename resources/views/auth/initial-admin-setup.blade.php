<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup amministratore</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f8fafc;
            color: #111827;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        main {
            width: min(100% - 32px, 420px);
            padding: 28px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 20px 45px rgba(15, 23, 42, .08);
        }

        h1 {
            margin: 0 0 8px;
            font-size: 1.35rem;
            line-height: 1.25;
        }

        p {
            margin: 0 0 24px;
            color: #4b5563;
            line-height: 1.5;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: .92rem;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 10px 12px;
            font: inherit;
        }

        input:focus {
            outline: 2px solid #f59e0b;
            outline-offset: 1px;
            border-color: #f59e0b;
        }

        .field {
            margin-bottom: 16px;
        }

        .error {
            margin-top: 6px;
            color: #b91c1c;
            font-size: .88rem;
        }

        button {
            width: 100%;
            border: 0;
            border-radius: 6px;
            padding: 11px 14px;
            background: #d97706;
            color: #ffffff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        button:hover {
            background: #b45309;
        }
    </style>
</head>
<body>
<main>
    <h1>Setup amministratore</h1>
    <p>Imposta le credenziali iniziali. Questi dati sostituiranno l'utente amministratore predefinito.</p>

    <form method="POST" action="{{ route('setup.store') }}">
        @csrf

        <div class="field">
            <label for="username">Nome utente</label>
            <input id="username" name="username" value="{{ old('username') }}" autocomplete="username" required autofocus>
            @error('username')
                <div class="error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>
            @error('password')
                <div class="error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <label for="password_confirmation">Conferma password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        </div>

        <button type="submit">Salva e accedi</button>
    </form>
</main>
</body>
</html>
