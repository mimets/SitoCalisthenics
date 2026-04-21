# SitoCalisthenics

Sito PHP per Calisthenics Trentino Academy, pronto per deploy su Render come web service Docker.

## Deploy su Render

1. Collega questa repo a Render.
2. Crea un Web Service Docker oppure sincronizza `render.yaml`.
3. Imposta queste variabili ambiente:
   - `SMTP_HOST`
   - `SMTP_PORT`
   - `SMTP_USERNAME`
   - `SMTP_PASSWORD`
   - `SMTP_FROM_EMAIL`
   - `SMTP_FROM_NAME`
   - `SMTP_TO_EMAIL`

## Note

- In `file:///` il form usa un fallback `mailto:`.
- Su Render o su un server HTTP reale, il form invia a `contact.php`.
