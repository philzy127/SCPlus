(function ($) {
  "use strict";

  $(function () {
    /**
     * Handles the dynamic behavior of the date format rule builder.
     */
    function initializeDateFormatRuleBuilder() {
      function toggleDateOptions($rule) {
        var formatType = $rule.find(".scp-date-format-type").val();
        var $customFormatInput = $rule.find(".scp-date-custom-format");
        var $dateOptions = $rule.find(".scp-date-options");

        if (formatType === "custom") {
          $customFormatInput.show();
          $dateOptions.hide();
        } else if (
          formatType === "date_only" ||
          formatType === "date_and_time"
        ) {
          $customFormatInput.hide();
          $dateOptions.show();
        } else {
          $customFormatInput.hide();
          $dateOptions.hide();
        }
      }

      // Initial setup on page load.
      $(".scp-date-rule").each(function () {
        toggleDateOptions($(this));
      });

      // Handle change event for the format type dropdown.
      $("#scp-date-rules-container").on(
        "change",
        ".scp-date-format-type",
        function () {
          toggleDateOptions($(this).closest(".scp-date-rule"));
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
