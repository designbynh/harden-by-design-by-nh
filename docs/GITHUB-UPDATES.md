# GitHub automatic updates (outside Cursor)

This plugin uses **Plugin Update Checker** (PUC) to offer updates from  
`https://github.com/designbynh/harden-by-design-by-nh/`.

WordPress will show an update on **Plugins** when the version on GitHub is **newer** than the `Version:` header in `harden-by-design-by-nh.php`.

---

## 1. Public repository (simplest)

1. Push all plugin files (including `lib/plugin-update-checker/` and `readme.txt`) to GitHub.
2. Default branch should be **`main`** (the checker is configured for `main`).
3. To release a new version:
   - Edit **`Version:`** in `harden-by-design-by-nh.php` (e.g. `1.3.5`).
   - Update **`Stable tag:`** in **`readme.txt`** to match (e.g. `1.3.5`).
   - Commit and push to `main`.
   - Create a **Git tag** that matches the version (e.g. `1.3.5` or `v1.3.5`).  
     In GitHub: **Releases → Draft a new release → choose tag** (create new tag) → publish.  
     Or locally:  
     `git tag 1.3.5 && git push origin 1.3.5`
4. On each WordPress site: wait up to **12 hours** for the check, or on **Plugins** use **“Check for updates”** (if shown) next to the plugin row.

PUC prefers **GitHub Releases** and **tags**; with default `main` it will use tags/releases before falling back to the branch.

---

## 2. Private repository

GitHub’s API requires authentication for private repos.

1. Create a **Personal Access Token** (classic: `repo` scope, or fine-grained read access to this repository).
2. In **`wp-config.php`** (above `That's all, stop editing!`):

   ```php
   define( 'HARDEN_BY_NH_GITHUB_TOKEN', 'ghp_your_token_here' );
   ```

3. Do **not** commit the token. Never commit `wp-config.php`.

---

## 3. After installing from a ZIP or manual copy

- The plugin folder can be named anything (e.g. `designbynh`). Updates replace the **folder** WordPress associates with this plugin; keep one install path per site.
- First update may require WordPress filesystem credentials (FTP/SSH) depending on server permissions.

---

## 4. Verify updates manually

- **Plugins →** find the plugin → look for update notice or **“Check for updates”**.
- Clear object cache if a host caches HTTP aggressively (rare for admin).

---

## 5. Optional filters (for developers)

In a small custom plugin or theme `functions.php`:

- `harden_by_nh_update_repository_url` — change the GitHub URL.
- `harden_by_nh_update_checker_slug` — change the internal PUC slug (only if you know you need it).

---

## 6. Updating the bundled PUC library

PUC lives in **`lib/plugin-update-checker/`**. To upgrade PUC:

1. Download the [latest release](https://github.com/YahnisElsts/plugin-update-checker/releases).
2. Replace the folder contents.
3. Update the `require_once` path in `includes/class-harden-updates.php` if the loader filename changes (e.g. `load-v5p7.php`).
