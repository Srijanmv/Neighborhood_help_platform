# Mobile Backend API

Base path: `/neighborhood_help/api/`

## Auth

- `POST auth/register.php`
- `POST auth/verify.php`
- `POST auth/login.php`
- `GET auth/me.php`
- `POST auth/logout.php`

## Posts

- `GET posts/index.php`
- `GET posts/show.php?id={postId}`
- `POST posts/index.php`
- `POST posts/comment.php`
- `POST posts/help.php`

## Messages

- `GET messages/index.php`
- `GET messages/thread.php?id={conversationId}`
- `POST messages/send.php`

## Auth header

Use `Authorization: Bearer <token>` for authenticated endpoints.
