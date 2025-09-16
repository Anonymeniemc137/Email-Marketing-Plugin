/* 
** Backend - Bulk Email Send page.
** Select 2 initialization and conditional logic.
*/

jQuery(document).ready(function($) {
    // Toggle user selection field visibility based on send type
    $("input[name=dg_em_send_type]").on("change", function() {
        if ($(this).val() === "specific") {
            // Initialize Select2 for user selection
            $("#dg_em_users").select2();
            $("#dg_em_users_wrapper").show();
        } else {
            $("#dg_em_users_wrapper").hide();
        }
    });
});