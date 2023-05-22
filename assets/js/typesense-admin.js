jQuery(document).ready(function ($) {
  $("#index-products-btn").on("click", function () {
    var data = {
      action: "index_products_in_typesense",
      security: typesenseAdminAjax.nonce,
    };

    $.post(typesenseAdminAjax.ajaxurl, data, function (response) {
      alert(response);
    });
  });
});


jQuery(document).ready(function($) {
    $(".region-checkbox").each(function() {
        var textField = $(this).closest('div').find(".region-message");
        if(!$(this).is(":checked")) {
            textField.hide();
        }
    });
    
    $(".region-checkbox").change(function() {
        var textField = $(this).closest('div').find(".region-message");
        if($(this).is(":checked")) {
            textField.show();
        } else {
            textField.hide();
        }
    });
});
