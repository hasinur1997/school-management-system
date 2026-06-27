<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Parents - {{ config('app.name', 'School Management') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="parents-shell" data-parents-app>
            <section class="parents-panel parents-auth" data-auth-panel>
                <div>
                    <p class="parents-kicker">School Management</p>
                    <h1>Parents</h1>
                </div>

                <form class="parents-auth-form" data-login-form>
                    <label>
                        <span>Login</span>
                        <input name="login" type="text" autocomplete="username" placeholder="Email or phone" required>
                    </label>

                    <label>
                        <span>Password</span>
                        <input name="password" type="password" autocomplete="current-password" required>
                    </label>

                    <button class="parents-button parents-button-primary" type="submit">Sign in</button>
                </form>

                <details class="parents-token-details">
                    <summary>Use existing token</summary>
                    <form class="parents-token-form" data-token-form>
                        <label>
                            <span>Bearer token</span>
                            <textarea name="token" rows="3" spellcheck="false"></textarea>
                        </label>
                        <button class="parents-button" type="submit">Use token</button>
                    </form>
                </details>

                <p class="parents-message" data-auth-message role="status"></p>
            </section>

            <section class="parents-workspace" data-workspace hidden>
                <header class="parents-toolbar">
                    <div>
                        <p class="parents-kicker">Parents</p>
                        <h1 data-view-title>Active parents</h1>
                    </div>

                    <div class="parents-toolbar-actions">
                        <button class="parents-button" type="button" data-refresh>Refresh</button>
                        <button class="parents-button" type="button" data-logout>Sign out</button>
                    </div>
                </header>

                <section class="parents-controls" aria-label="Parent list controls">
                    <div class="parents-tabs" role="tablist" aria-label="Parent list view">
                        <button class="parents-tab is-active" type="button" data-view="active">Active</button>
                        <button class="parents-tab" type="button" data-view="trash">Trash</button>
                    </div>

                    <form class="parents-search" data-search-form>
                        <label>
                            <span>Search</span>
                            <input name="search" type="search" placeholder="Name or phone">
                        </label>
                        <button class="parents-button" type="submit">Search</button>
                    </form>
                </section>

                <section class="parents-bulkbar" data-bulkbar hidden>
                    <span data-selected-count>0 selected</span>
                    <div class="parents-bulk-actions" data-active-bulk-actions>
                        <button class="parents-button parents-button-danger" type="button" data-bulk-delete>Delete</button>
                    </div>
                    <div class="parents-bulk-actions" data-trash-bulk-actions hidden>
                        <button class="parents-button" type="button" data-bulk-restore>Restore</button>
                        <button class="parents-button parents-button-danger" type="button" data-bulk-force-delete>Delete permanently</button>
                    </div>
                </section>

                <section class="parents-panel parents-table-panel">
                    <div class="parents-table-scroll">
                        <table class="parents-table">
                            <thead>
                                <tr>
                                    <th class="parents-select-cell">
                                        <input type="checkbox" data-select-all aria-label="Select all parents">
                                    </th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>Relation</th>
                                    <th>Students</th>
                                    <th data-trash-heading hidden>Deleted</th>
                                    <th class="parents-actions-cell">Actions</th>
                                </tr>
                            </thead>
                            <tbody data-parent-rows></tbody>
                        </table>
                    </div>

                    <div class="parents-empty" data-empty hidden>No parents found.</div>
                    <div class="parents-loading" data-loading hidden>Loading parents...</div>
                    <p class="parents-message" data-list-message role="status"></p>
                </section>

                <footer class="parents-pagination">
                    <button class="parents-button" type="button" data-prev-page>Previous</button>
                    <span data-page-summary>Page 1 of 1</span>
                    <button class="parents-button" type="button" data-next-page>Next</button>
                </footer>
            </section>
        </main>
    </body>
</html>
