/**
 * Admin JavaScript for Bulk Post Importer
 *
 * @package Bulk_Post_Importer
 */

(function ($) {
  "use strict";

  /**
   * Custom fields table management
   */
  const CustomFieldsTable = {
    /**
     * Initialize
     */
    init: function () {
      this.table = $("#bji-custom-fields-table");
      if (!this.table.length) {
        return;
      }

      this.tableBody = this.table.find("tbody");
      this.addButton = $("#bji-add-custom-field");
      this.rowIndex = this.tableBody.find("tr").length;

      this.bindEvents();
    },

    /**
     * Bind events
     */
    bindEvents: function () {
      // Add row button click
      this.addButton.on("click", this.addRow.bind(this));

      // Remove row button click (event delegation)
      this.tableBody.on("click", ".bji-remove-row", this.removeRow.bind(this));
    },

    /**
     * Add a new row
     */
    addRow: function () {
      const newRow = this.createRow(this.rowIndex);
      this.tableBody.append(newRow);
      this.rowIndex++;
    },

    /**
     * Remove a row
     */
    removeRow: function (e) {
      e.preventDefault();
      $(e.target).closest("tr").remove();
    },

    /**
     * Create a new row HTML
     */
    createRow: function (index) {
      const jsonKeySelect = this.createJsonKeySelect(index);
      const metaKeyInput = this.createMetaKeyInput(index);
      const removeButton = this.createRemoveButton();

      return `
				<tr valign="top" class="bulkpostimporter-custom-field-row">
					<td>${jsonKeySelect}</td>
					<td>${metaKeyInput}</td>
					<td>${removeButton}</td>
				</tr>
			`;
    },

    /**
     * Create JSON key select dropdown
     */
    createJsonKeySelect: function (index) {
      const options = this.getJsonKeyOptions();
      return `<select name="mapping[custom][${index}][json_key]">${options}</select>`;
    },

    /**
     * Create meta key input
     */
    createMetaKeyInput: function (index) {
      return `<input type="text" name="mapping[custom][${index}][meta_key]" placeholder="${bulkpostimporterAdmin.strings.enterMetaKey}" />`;
    },

    /**
     * Create remove button
     */
    createRemoveButton: function () {
      return `<button type="button" class="button bji-remove-row">${bulkpostimporterAdmin.strings.removeRow}</button>`;
    },

    /**
     * Get JSON key options from existing select
     */
    getJsonKeyOptions: function () {
      const firstSelect = this.tableBody.find("select:first");
      return firstSelect.length
        ? firstSelect.html()
        : `<option value="">${bulkpostimporterAdmin.strings.doNotMap}</option>`;
    },
  };

  /**
   * File validation and feedback
   */
  const FileValidation = {
    /**
     * Initialize
     */
    init: function () {
      this.form = $('form[enctype="multipart/form-data"]');
      this.fileInput = $("#bulkpostimporter_json_file");
      this.submitButton = this.form.find('input[type="submit"]');
      
      if (!this.form.length || !this.fileInput.length) {
        return;
      }

      this.createFeedbackElements();
      this.bindEvents();
    },

    /**
     * Create feedback elements
     */
    createFeedbackElements: function () {
      // Add feedback container after file input
      this.feedbackContainer = $('<div class="bulkpostimporter-file-feedback"></div>');
      this.fileInput.closest('td').append(this.feedbackContainer);
      
      // Add file info container
      this.fileInfoContainer = $('<div class="bulkpostimporter-file-info"></div>');
      this.feedbackContainer.append(this.fileInfoContainer);
    },

    /**
     * Bind events
     */
    bindEvents: function () {
      this.fileInput.on("change", this.handleFileSelect.bind(this));
      this.form.on("submit", this.validateForm.bind(this));
    },

    /**
     * Handle file selection
     */
    handleFileSelect: function (e) {
      const file = e.target.files[0];
      
      if (!file) {
        this.clearFeedback();
        this.disableSubmit();
        return;
      }

      this.validateFile(file);
    },

    /**
     * Validate selected file
     */
    validateFile: function (file) {
      const validation = this.performValidation(file);
      
      if (validation.isValid) {
        this.showSuccess(file, validation);
        this.enableSubmit();
      } else {
        this.showError(validation.errors);
        this.disableSubmit();
      }
    },

    /**
     * Perform file validation
     */
    performValidation: function (file) {
      const errors = [];
      let isValid = true;

      // Check if file is empty
      if (file.size === 0) {
        errors.push('File is empty. Please upload a file with data.');
        isValid = false;
      }

      // Check file size (10MB limit)
      const maxSize = 10 * 1024 * 1024; // 10MB in bytes
      if (file.size > maxSize) {
        errors.push(bulkpostimporterAdmin.strings.fileSizeError);
        isValid = false;
      }

      // Check file type
      const fileName = file.name.toLowerCase();
      const validTypes = ["application/json", "text/csv", "text/plain", "application/csv"];
      const validExtensions = [".json", ".csv"];
      
      const hasValidType = validTypes.includes(file.type);
      const hasValidExtension = validExtensions.some(ext => fileName.endsWith(ext));
      
      if (!hasValidType && !hasValidExtension) {
        errors.push(bulkpostimporterAdmin.strings.fileTypeError);
        isValid = false;
      }

      // Try to validate JSON/CSV content
      if (isValid && file.size > 0) {
        this.validateFileContent(file, errors);
      }

      return {
        isValid: errors.length === 0,
        errors: errors,
        fileSize: this.formatFileSize(file.size),
        fileType: fileName.endsWith('.json') ? 'JSON' : 'CSV'
      };
    },

    /**
     * Validate file content
     */
    validateFileContent: function (file, errors) {
      const reader = new FileReader();
      const self = this;
      
      reader.onload = function(e) {
        const content = e.target.result;
        const fileName = file.name.toLowerCase();
        
        if (fileName.endsWith('.json')) {
          self.validateJsonContent(content, errors);
        } else if (fileName.endsWith('.csv')) {
          self.validateCsvContent(content, errors);
        }
        
        // Update feedback after content validation
        setTimeout(function() {
          if (errors.length > 0) {
            self.showError(errors);
            self.disableSubmit();
          }
        }, 100);
      };
      
      reader.onerror = function() {
        errors.push('Unable to read file content.');
      };
      
      // Read first 1KB to validate structure
      reader.readAsText(file.slice(0, 1024));
    },

    /**
     * Validate JSON content structure
     */
    validateJsonContent: function (content, errors) {
      try {
        // Remove whitespace and check if content is essentially empty
        const trimmedContent = content.trim();
        if (!trimmedContent || trimmedContent === '{}' || trimmedContent === '[]') {
          errors.push('JSON file contains no data. Please provide an array with at least one object.');
          return;
        }
        
        const data = JSON.parse(content);
        
        if (!Array.isArray(data)) {
          errors.push('JSON file must contain an array of objects, not a single object.');
          return;
        }
        
        if (data.length === 0) {
          errors.push('JSON file contains an empty array. Please provide at least one object with data.');
          return;
        }
        
        if (data.length > 0 && (typeof data[0] !== 'object' || data[0] === null)) {
          errors.push('JSON array must contain objects, not primitive values or null.');
          return;
        }
        
        // Check if the first object has any properties
        if (data.length > 0 && Object.keys(data[0]).length === 0) {
          errors.push('JSON objects must have at least one property (field) to import.');
          return;
        }
        
      } catch (e) {
        errors.push('Invalid JSON format: ' + e.message);
      }
    },

    /**
     * Validate CSV content structure
     */
    validateCsvContent: function (content, errors) {
      const trimmedContent = content.trim();
      if (!trimmedContent) {
        errors.push('CSV file is empty. Please provide a file with headers and data.');
        return;
      }
      
      const lines = content.split('\n').filter(line => line.trim());
      
      if (lines.length < 2) {
        errors.push('CSV file must have at least a header row and one data row.');
        return;
      }
      
      // Check if first line has headers
      const firstLine = lines[0].trim();
      if (!firstLine) {
        errors.push('CSV file must have a header row with column names.');
        return;
      }
      
      const headers = firstLine.split(',').map(h => h.trim().replace(/"/g, ''));
      if (headers.length < 1 || headers.every(h => !h)) {
        errors.push('CSV file must have at least one valid column header.');
        return;
      }
      
      // Check if there's at least one data row with content
      const dataLines = lines.slice(1);
      const hasValidData = dataLines.some(line => {
        const values = line.split(',').map(v => v.trim().replace(/"/g, ''));
        return values.some(value => value.length > 0);
      });
      
      if (!hasValidData) {
        errors.push('CSV file must have at least one row with actual data.');
        return;
      }
    },

    /**
     * Show success feedback
     */
    showSuccess: function (file, validation) {
      const successHtml = `
        <div class="bulkpostimporter-success-feedback">
          <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
          <strong>File looks good!</strong>
          <div class="file-details">
            <span>Type: ${validation.fileType}</span> | 
            <span>Size: ${validation.fileSize}</span>
          </div>
        </div>
      `;
      
      this.fileInfoContainer.html(successHtml);
      this.feedbackContainer.removeClass('error').addClass('success');
    },

    /**
     * Show error feedback
     */
    showError: function (errors) {
      const errorHtml = `
        <div class="bulkpostimporter-error-feedback">
          <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
          <strong>File validation failed:</strong>
          <ul>
            ${errors.map(error => `<li>${error}</li>`).join('')}
          </ul>
        </div>
      `;
      
      this.fileInfoContainer.html(errorHtml);
      this.feedbackContainer.removeClass('success').addClass('error');
    },

    /**
     * Clear feedback
     */
    clearFeedback: function () {
      this.fileInfoContainer.empty();
      this.feedbackContainer.removeClass('success error');
    },

    /**
     * Enable submit button
     */
    enableSubmit: function () {
      this.submitButton.prop('disabled', false);
    },

    /**
     * Disable submit button
     */
    disableSubmit: function () {
      this.submitButton.prop('disabled', true);
    },

    /**
     * Format file size
     */
    formatFileSize: function (bytes) {
      if (bytes === 0) return '0 Bytes';
      
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },

    /**
     * Validate form before submission (backup validation)
     */
    validateForm: function (e) {
      const file = this.fileInput[0].files[0];

      if (!file) {
        return true; // Let HTML5 validation handle this
      }

      const validation = this.performValidation(file);
      
      if (!validation.isValid) {
        e.preventDefault();
        this.showError(validation.errors);
        return false;
      }

      return true;
    }
  };

  /**
   * Auto-mapping suggestions
   */
  const AutoMapping = {
    /**
     * Initialize
     */
    init: function () {
      this.mappingTable = $(".bulkpostimporter-mapping-table");
      if (!this.mappingTable.length) {
        return;
      }

      this.suggestMappings();
    },

    /**
     * Suggest automatic mappings based on field names
     */
    suggestMappings: function () {
      // This is already handled server-side, but we could add
      // client-side enhancements here if needed
    },
  };

  /**
   * Progress indicator
   */
  const ProgressIndicator = {
    /**
     * Initialize
     */
    init: function () {
      this.form = $("form");
      this.submitButton = this.form.find('input[type="submit"]');

      this.bindEvents();
    },

    /**
     * Bind events
     */
    bindEvents: function () {
      this.form.on("submit", this.showProgress.bind(this));
    },

    /**
     * Show progress indicator
     */
    showProgress: function () {
      if (this.submitButton.length) {
        this.submitButton.prop("disabled", true);
        this.submitButton.val(this.submitButton.val() + "...");
      }
    },
  };

  /**
   * Initialize when document is ready
   */
  $(document).ready(function () {
    CustomFieldsTable.init();
    FileValidation.init();
    AutoMapping.init();
    ProgressIndicator.init();
  });
})(jQuery);
