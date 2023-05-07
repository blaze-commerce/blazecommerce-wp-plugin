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
