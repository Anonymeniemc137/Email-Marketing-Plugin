<?php
/**
 * Email status management interface.
 *
 * @package EmailMarketingPlugin
 */

 defined( 'ABSPATH' ) || exit;

 if ( ! class_exists( 'WP_List_Table' ) ) {
     require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
 }
 
/**
 * Custom table class for email status logs.
 */
class DG_Email_Status_Table extends WP_List_Table
{   
    /**
     * Initialize table.
     */
    public function __construct()
    {
        parent::__construct([
            'singular' => 'email_status',
            'plural'   => 'email_statuses',
            'ajax'     => false,
        ]);
    }

    /**
     * Table columns.
     *
     * @return array Column headers.
     */
    public function get_columns()
    {
        return [
            'cb' => '<input type="checkbox" id="cb-select-all-1" />',
            'email'        => 'Email',
            'template'     => 'Template',
            'status'       => 'Status',
            'scheduled_at' => 'Scheduled At',
            'log_details'  => 'Log Details',
        ];
    }

    /**
     * Bulk actions.
     *
     * @return array Available actions.
     */
    public function get_bulk_actions()
    {
        return [
            'delete' => 'Delete',
        ];
    }

    /**
     * Process bulk actions.
     */
    public function process_bulk_action()
    {
        if ($this->current_action() === 'delete' && !empty($_REQUEST['email_status_ids'])) {
            global $wpdb;
            $ids = array_map('intval', (array) $_REQUEST['email_status_ids']);
            foreach ($ids as $id) {
                $deleted = wp_delete_post($id, true); 
                $wpdb->delete($wpdb->postmeta, ['post_id' => $id]);
                if (!$deleted) {
                    error_log("Failed to delete post ID: $id");
                }
            }

            // Safe redirect
            wp_safe_redirect(remove_query_arg(['action', 'action2', '_wp_http_referer']));
            exit;
        }
    }

    /**
     * Prepare table items.
     */
    public function prepare_items()
    {
        $per_page = 100;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        $status_filter = isset($_REQUEST['post_status']) ? sanitize_key($_REQUEST['post_status']) : '';

        // Query arguments.
        $args = [
            'post_type'      => 'dg_em_email_status',
            'post_status'    => ['scheduled', 'failed', 'sent', 'processing'],
            'posts_per_page' => $per_page,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'DESC',
        ];

        // Status filter.
        if ($status_filter && in_array($status_filter, ['scheduled', 'failed', 'sent', 'processing'])) {
            $args['post_status'] = $status_filter;
        }

        // Search filter.
        if (!empty($_REQUEST['s'])) {
            $args['s'] = sanitize_text_field($_REQUEST['s']);
        }

        // Month filter.
        if (!empty($_REQUEST['m']) && $_REQUEST['m'] !== '0') {
            $year  = substr($_REQUEST['m'], 0, 4);
            $month = substr($_REQUEST['m'], 4, 2);
            $args['date_query'] = [
                [
                    'year'  => (int) $year,
                    'month' => (int) $month,
                ]
            ];
        }

        $posts = get_posts($args);
        $formatted_items = [];

        foreach ($posts as $post) {
            $id           = $post->ID;
            $email        = get_post_meta($id, '_dg_em_customer_email', true);
            $template_id  = get_post_meta($id, '_dg_em_template_id', true);
            $template     = $template_id ? get_the_title($template_id) : 'N/A';
            $status       = get_post_status($id);
            $scheduled_at = get_the_date('Y-m-d h:i:s', $id);
            $log_details  = $post->post_content;

            $formatted_items[] = [
                'id'           => $id,
                'email'        => $email,
                'template'     => $template,
                'status'       => $status,
                'scheduled_at' => $scheduled_at,
                'log_details'  => $log_details,
                'cb'           => sprintf('<input type="checkbox" style="margin: 0 0 0 8px !important;" name="email_status_ids[]" value="%s" />', $id),
            ];
        }

        $this->items = $formatted_items;

        // Pagination setup.
        $count_args = [
            'post_type'      => 'dg_em_email_status',
            'post_status'    => $args['post_status'],
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];

        if (!empty($args['date_query'])) {
            $count_args['date_query'] = $args['date_query'];
        }

        $total_items = count(get_posts($count_args));

        
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }

    /**
     * Render checkbox column.
     *
     * @param array $item Current row item.
     * @return string HTML output.
     */
    public function column_cb($item)
    {
        return $item['cb'];
    }

    /**
     * Default column renderer.
     *
     * @param array  $item Current row item.
     * @param string $column_name Column name.
     * @return string Column value.
     */
    public function column_default($item, $column_name)
    {
        if ($column_name === 'cb') {
            return $item['cb'];
        }
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
    }

    public function display_rows()
    {
        echo '<table class="wp-list-table widefat fixed striped">';

            // Head
            echo '<thead><tr>';
            foreach ($this->get_columns() as $column_name => $column_label) {
                $class = "manage-column column-$column_name";

                if ($column_name === 'cb') {
                    echo "<th scope='col' class='$class'  style='width:1%'><input type='checkbox' id='cb-select-all' /></th>";
                } else {
                    echo "<th scope='col' class='$class'>$column_label</th>";
                }
            }
            echo '</tr></thead>';

            // Body
            echo '<tbody>';
            foreach ($this->items as $item) {
                echo '<tr>';
                foreach ($this->get_columns() as $column_name => $column_label) {
                    $class = "class='$column_name column-$column_name'";
                    $data = $this->column_default($item, $column_name);
                    echo "<td $class>$data</td>";
                }
                echo '</tr>';
            }
            echo '</tbody>';
        echo '</table>';
        
        // Select all logic
        echo <<<EOD
            <script>
            jQuery(document).ready(function($) {
                $('#cb-select-all').on('change', function() {
                    const checked = $(this).is(':checked');
                    $("input[name='email_status_ids[]']").prop('checked', checked);
                });

                $("input[name='email_status_ids[]']").on('change', function() {
                    if (!$(this).is(':checked')) {
                        $('#cb-select-all').prop('checked', false);
                    } else {
                        const total = $("input[name='email_status_ids[]']").length;
                        const checked = $("input[name='email_status_ids[]']:checked").length;
                        if (total === checked) {
                            $('#cb-select-all').prop('checked', true);
                        }
                    }
                });
            });
            </script>
            <style>
                
                .wp-list-table td.cb{
                    margin: 0 auto !important;
                }

            </style>
        EOD;
    }

    /**
     * Extra table navigation.
     *
     * @param string $which Top or bottom navigation.
     */
    public function extra_tablenav($which)
    {
        if ($which !== 'top') return;

        global $wpdb;
        $months = $wpdb->get_results("
            SELECT DISTINCT YEAR(post_date) AS year, MONTH(post_date) AS month
            FROM $wpdb->posts
            WHERE post_type = 'dg_em_email_status'
            ORDER BY post_date DESC
        ");

        $selected_month = $_GET['m'] ?? '';

        echo '<div class="alignleft actions">';
        echo '<select name="m">';
        echo '<option value="0">All dates</option>';

        foreach ($months as $month) {
            $value = sprintf('%04d%02d', $month->year, $month->month);
            $label = date('F Y', mktime(0, 0, 0, $month->month, 1, $month->year));
            $selected = selected($selected_month, $value, false);
            echo "<option value='$value' $selected>$label</option>";
        }

        echo '</select>';
        submit_button('Filter', '', 'filter_action', false);
        echo '</div>';
    }

    /**
     * Table views.
     *
     * @return array Filter views.
     */
    public function get_views()
    {
        $base_url = admin_url('admin.php?page=dg_em_status_page');
        $statuses = [
            'all'        => 'All',
            'scheduled'  => 'Scheduled',
            'processing' => 'Processing',
            'sent'       => 'Sent',
            'failed'     => 'Failed',
        ];

        $views = [];
        foreach ($statuses as $status_key => $label) {
            $count = $this->get_post_count($status_key);
            $class = '';

            if (($status_key === 'all' && !isset($_REQUEST['post_status'])) ||
                (isset($_REQUEST['post_status']) && $_REQUEST['post_status'] === $status_key)
            ) {
                $class = 'current';
            }

            $url = $status_key === 'all'
                ? $base_url
                : add_query_arg('post_status', $status_key, $base_url);

            $views[$status_key] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url($url),
                $class,
                $label,
                $count
            );
        }

        return $views;
    }

    /**
     * Get post count by status.
     *
     * @param string $status Post status.
     * @return int Post count.
     */
    private function get_post_count($status)
    {
        $counts = wp_count_posts('dg_em_email_status');
        if ($status === 'all') {
            return ($counts->scheduled ?? 0) + ($counts->processing ?? 0) + ($counts->sent ?? 0) + ($counts->failed ?? 0);
        }
        return $counts->$status ?? 0;
    }

    public function bulk_actions($which = '')
    {
        $actions = $this->get_bulk_actions();
        if (empty($actions)) return;

        echo '<div class="alignleft actions bulkactions">';
        echo '<label for="bulk-action-selector-' . $which . '" class="screen-reader-text">Select bulk action</label>';
        echo '<select name="' . esc_attr( $which === 'bottom' ? 'action2' : 'action' ) . '" id="bulk-action-selector-' . $which . '">';
        echo '<option value="-1">Bulk actions</option>';

        foreach ($actions as $action => $label) {
            echo '<option value="' . esc_attr($action) . '">' . esc_html($label) . '</option>';
        }

        echo '</select>';
        submit_button(__('Apply'), ['id' => "doaction$which", 'name' => $which === 'bottom' ? 'doaction2' : 'doaction'], '', false);
        echo '</div>';
    }

}


add_action('admin_init', 'dg_em_process_status_bulk_actions');
/**
 * Process bulk actions.
 */
function dg_em_process_status_bulk_actions() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'dg_em_status_page') {
        return;
    }

    $table = new DG_Email_Status_Table();
    $action = $table->current_action();
    
    if ($action === 'delete' && !empty($_REQUEST['email_status_ids'])) {
        global $wpdb;
        $ids = array_map('intval', (array) $_REQUEST['email_status_ids']);
        foreach ($ids as $id) {
            $deleted = wp_delete_post($id, true); 
            $wpdb->delete($wpdb->postmeta, ['post_id' => $id]);
        }

        // Safe redirect
        wp_safe_redirect(remove_query_arg(['action', 'action2', '_wp_http_referer', 'email_status_ids']));
        exit;
    }
}


/**
 * Render status page.
 */
function dg_em_status_page_callback()
{
    // Initialize the table
    $table = new DG_Email_Status_Table();

    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">Email Status Logs</h1>';

    $table->prepare_items();

    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="dg_em_status_page" />';
    echo '<div class="tablenav top">';
    echo $table->views();
    echo '</div>';
    $table->search_box('Search Email Logs', 'email_logs');
    echo '</form>';

    // Use POST form for bulk actions
    echo '<form method="post">';
    $table->display();
    echo '</form>';
    echo '</div>';
}