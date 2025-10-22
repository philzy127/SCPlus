(function ($) {
  "use strict";

  $(function () {
    /**
     * Handles the dynamic behavior of the date format rule builder.
     */
    function initializeDateFormatRuleBuilder() {
      // Show/hide custom format field on initial load.
      $(".scp-date-format-type").each(function () {
        var $this = $(this);
        var $customFormatInput = $this
          .closest(".scp-date-rule")
          .find(".scp-date-custom-format");
        if ($this.val() === "custom") {
          $customFormatInput.show();
        } else {
          $customFormatInput.hide();
        }
      });

      // Handle change event for the format type dropdown.
      $("#scp-date-rules-container").on(
        "change",
        ".scp-date-format-type",
        function () {
          var $this = $(this);
          var $customFormatInput = $this
            .closest(".scp-date-rule")
            .find(".scp-date-custom-format");
          if ($this.val() === "custom") {
            $customFormatInput.show();
          } else {
            $customFormatInput.hide();
          }
        }
      );

      // Add a new rule.
      $("#scp-add-date-rule").on("click", function () {
        var ruleTemplate = $("#scp-date-rule-template").html();
        var ruleCount = $("#scp-date-rules-container .scp-date-rule").length;
        var newRule = ruleTemplate.replace(/__INDEX__/g, ruleCount);
        $("#scp-no-date-rules-message").hide();
        $("#scp-date-rules-container").append(newRule);
      });

      // Remove a rule.
      $("#scp-date-rules-container").on(
        "click",
        ".scp-remove-date-rule",
        function () {
          $(this).closest(".scp-date-rule").remove();
          if ($("#scp-date-rules-container .scp-date-rule").length === 0) {
            $("#scp-no-date-rules-message").show();
          }
        }
      );
    }

    // Initialize the rule builder if we are on the correct page.
    if ($("#scp-date-rules-container").length) {
      initializeDateFormatRuleBuilder();
    }
  });
})(jQuery);
