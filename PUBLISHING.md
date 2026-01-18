# Publishing `rasedi/php-sdk`

This guide details the steps to publish your SDK to Packagist so it can be installed via Composer.

## Prerequisites
- A [GitHub](https://github.com) account.
- A [Packagist](https://packagist.org) account.

## Step 1: Push to GitHub

1.  **Create a new repository** on GitHub (e.g., `rasedi-php-sdk`).
2.  **Initialize git** (if not already done) and commit your changes:
    ```bash
    git init
    git add .
    git commit -m "Initial release ready for publication"
    ```
3.  **Link remote and push**:
    ```bash
    git remote add origin https://github.com/<YOUR_USERNAME>/rasedi-php-sdk.git
    git branch -M main
    git push -u origin main
    ```

## Step 2: Create a Release (Tagging)

Packagist requires version tags to manage releases.

1.  **Tag the release**:
    ```bash
    git tag v1.0.0
    git push origin v1.0.0
    ```
    *Alternatively, you can create a Release in the GitHub UI.*

## Step 3: Submit to Packagist

1.  Log in to [Packagist](https://packagist.org).
2.  Click **Submit** in the top menu.
3.  Enter your GitHub repository URL (e.g., `https://github.com/<YOUR_USERNAME>/rasedi-php-sdk`).
4.  Click **Check**.
5.  If valid, click **Submit**.

## Step 4: Set up Auto-Update (Webhooks)

To ensure Packagist updates automatically when you push new code:

1.  Go to your GitHub repository **Settings** > **Webhooks**.
2.  Add a new webhook.
    - **Payload URL**: `https://packagist.org/api/github`
    - **Content type**: `application/json`
    - **Secret**: (Get this from your Packagist profile page: "Show API Token")
3.  Click **Add webhook**.

---

**Success!** Your package should now be installable via:
```bash
composer require rasedi/php-sdk
```
