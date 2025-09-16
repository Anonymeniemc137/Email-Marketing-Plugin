  <header>
    <h1>Yeemail Extension – Advanced Email Automation</h1>
    <p class="meta">A custom WordPress plugin that extends <strong>Yeemail</strong> to enable automated, scheduled, and targeted email campaigns with advanced logging.</p>
  </header>

  <section class="section">
    <h2>Features</h2>
    <ul>
      <li><strong>Automated Campaigns</strong> — Send Yeemail templates to your customer base using scheduled cron jobs.</li>
      <li><strong>Smart Scheduling</strong> — Emails are dispatched with a configurable interval (default: 1 minute) between sends to avoid server overload.</li>
      <li><strong>Targeted Emails</strong> — Send campaigns to specific users or groups instead of the entire list.</li>
      <li><strong>Detailed Logs</strong> — Maintain a complete log of sent emails with delivery status and timestamps.</li>
      <li><strong>Performance Optimized</strong> — Batch processing and safeguards for large mailing lists.</li>
    </ul>
  </section>

  <section class="section">
    <h2>Installation</h2>
    <ol>
      <li>Upload the plugin folder to <code>/wp-content/plugins/</code> or install via the WordPress admin <em>Plugins → Add New → Upload Plugin</em>.</li>
      <li>Activate the plugin in the WordPress admin.</li>
      <li>Ensure the <strong>Yeemail</strong> plugin is installed and active.</li>
      <li>Open the plugin settings page to configure your campaign options and scheduling interval.</li>
    </ol>
  </section>

  <section class="section">
    <h2>Usage</h2>
    <ol>
      <li>Create email templates using the Yeemail UI.</li>
      <li>From the plugin dashboard, choose the template and select the audience (all users or specific users/groups).</li>
      <li>Schedule the campaign or run it immediately — the cron job will handle sending automatically.</li>
      <li>Monitor progress and delivery via the logs page; retry or re-send as needed.</li>
    </ol>
  </section>

  <section class="section">
    <h2>Requirements</h2>
    <ul>
      <li>WordPress 5.5+</li>
      <li>PHP 7.4+</li>
      <li>Yeemail plugin (active)</li>
    </ul>
  </section>

  <section class="section">
    <h2>Technical notes</h2>
    <p>The plugin uses a cron-based scheduler with batch processing to avoid timeouts. It stores logs in a custom database table and exposes status via the dashboard. Recommended to test on staging before running large campaigns on production.</p>
    <pre><code>// Example: Cron schedule registration (pseudo-code)
add_action('yeemail_ext_send_batch', 'yeemail_ext_send_batch_handler');
function yeemail_ext_send_batch_handler() {
  // fetch pending emails
  // send chunk (e.g. 50 emails)
  // log delivery status
  // schedule next batch
}
</code></pre>
  </section>

  <section class="section">
    <h2>Roadmap</h2>
    <ul>
      <li>Adjustable batch sizes and interval tuning via UI</li>
      <li>Advanced analytics & reporting dashboard</li>
      <li>WooCommerce integration for order-based segmentation</li>
      <li>Retry strategies and exponential backoff for transient mail errors</li>
    </ul>
  </section>

  <section class="section">
    <h2>License</h2>
    <p>This project is licensed under the <strong>MIT License</strong>. See the <code>LICENSE</code> file for details.</p>
  </section>

  <footer class="section">
    <p>If you’d like a more developer-focused README (with DB schema, hooks, filters, and code examples), I can expand this into a full developer guide.</p>
  </footer>

