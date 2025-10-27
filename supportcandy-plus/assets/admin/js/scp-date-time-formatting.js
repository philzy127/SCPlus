(function ($) {
  "use strict";

  $(function () {
    /**
     * Handles the dynamic behavior of the date format rule builder.
     */
    function initializeDateFormatRuleBuilder() {
      function toggleDateOptions($rule) {
        var formatType = $rule.find(".scp-date-format-type").val();
        var $bottomRow = $rule.find(".scp-date-rule-row-bottom");
        var $customFormatWrapper = $rule.find(".scp-custom-format-wrapper");

        // Handle visibility of the custom format input.
        if (formatType === "custom") {
          $customFormatWrapper.show();
        } else {
          $customFormatWrapper.hide();
        }

        // Handle visibility of the checkboxes row.
        if (formatType === "date_only" || formatType === "date_and_time") {
          $bottomRow.show();
        } else {
          $bottomRow.hide();
          // Uncheck the checkboxes when they are hidden to prevent saving unwanted values.
          $bottomRow.find('input[type="checkbox"]').prop("checked", false);
        }
      }

      // Initial setup on page load.
      $(".scp-date-rule-wrapper").each(function () {
        toggleDateOptions($(this));
      });

      // Handle change event for the format type dropdown.
      $("#scp-date-rules-container").on(
        "change",
        ".scp-date-format-type",
        function () {
          toggleDateOptions($(this).closest(".scp-date-rule-wrapper"));
        }
      );

      // Add a new rule.
      $("#scp-add-date-rule").on("click", function () {
        var ruleTemplate = $("#scp-date-rule-template").html();
        var newIndex = new Date().getTime(); // Use a timestamp for a unique index.
        var newRule = ruleTemplate.replace(/__INDEX__/g, newIndex);
        $("#scp-no-date-rules-message").hide();
        $("#scp-date-rules-container").append(newRule);
      });

      // Remove a rule.
      $("#scp-date-rules-container").on(
        "click",
        ".scp-remove-date-rule",
        function () {
          $(this).closest(".scp-date-rule-wrapper").remove();
          if ($("#scp-date-rules-container .scp-date-rule-wrapper").length === 0) {
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
