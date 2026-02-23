# OAuth Backend (PHP)

This folder contains a minimal PHP endpoint for GitHub OAuth token exchange.

## Files

- `oauth_github_token.php`: exchange `code` + `code_verifier` for GitHub `access_token`
- `.env.example`: environment variable example

## 1. Deploy

Upload these two files to the same directory on your HTTPS server:

- `oauth_github_token.php`
- `.env` (copy from `.env.example`)

For example endpoint:

- `https://api.yourdomain.com/oauth_github_token.php`

## 2. Simplest `.env` setup (recommended)

Create `oauth-backend/.env`:

```env
GITHUB_CLIENT_ID=Ov23lidxgKkwwJQnwxZk
GITHUB_CLIENT_SECRET=your_github_oauth_client_secret
```

The PHP script auto-loads `.env` from its own directory.

## 3. Optional: server environment variables

If you prefer not to use `.env`, you can inject env vars in Nginx/PHP-FPM:

```nginx
location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;

    fastcgi_param GITHUB_CLIENT_ID Ov23lidxgKkwwJQnwxZk;
    fastcgi_param GITHUB_CLIENT_SECRET your_github_client_secret;

    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
}
```

Reload services after change:

```bash
sudo nginx -t && sudo systemctl reload nginx
sudo systemctl restart php8.2-fpm
```

## 4. Quick API test

```bash
curl -X POST 'https://api.yourdomain.com/oauth_github_token.php' \
  -H 'Content-Type: application/json' \
  -d '{"code":"test","code_verifier":"test","redirect_uri":"fsnotes://oauth/callback"}'
```

If the endpoint responds with JSON (even an OAuth error), the server path is working.

## 5. iOS app config

Set in `FSNotes iOS/Info.plist`:

- `GitHubOAuthClientID`: `Ov23lidxgKkwwJQnwxZk`
- `GitHubOAuthRedirectURI`: `fsnotes://oauth/callback`
- `GitHubOAuthBackendTokenURL`: `https://api.yourdomain.com/oauth_github_token.php`

## 6. GitHub OAuth App settings

In GitHub OAuth App:

- `Authorization callback URL`: `fsnotes://oauth/callback`

Note: keep `GITHUB_CLIENT_SECRET` only on backend, never in iOS app.
# github-oauth-backend
