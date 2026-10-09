/**
 * [DEPRECATED / OBSOLETE]
 * Google Apps Script Web App for Tabeeb Contractor.
 * Superseded by the secure MySQL same-origin endpoint: POST /api/leads
 * Kept for historical reference only. Do not deploy to production.
 */

function doPost(e) {
  try {
    // Parse form input payload
    var data = JSON.parse(e.postData.contents);
    
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
    
    // Setup headers on the first row if sheet is empty
    if (sheet.getLastRow() === 0) {
      sheet.appendRow(["Timestamp", "Name", "Phone", "Email", "Budget", "Service Requested", "Message", "Consent Acknowledged", "Company"]);
    }
    
    // Format phone number to prevent formula parse error if it starts with '+'
    var phoneFormatted = data.phone || "";
    if (phoneFormatted.toString().indexOf('+') === 0) {
      phoneFormatted = "'" + phoneFormatted;
    }
    
    // Log details as a new row
    sheet.appendRow([
      data.submittedAt || new Date().toLocaleString(),
      data.name || "",
      phoneFormatted,
      data.email || "",
      data.budget || "N/A (Home Page Form)",
      data.service || "",
      data.message || "",
      data.consent ? "Yes" : "N/A",
      data.companyName || "Tabeeb Contractor Pte Ltd."
    ]);
    
    // Send email alert to recipients
    var emailRecipients = "info@tabeebgroup.com";
    var subject = "Tabeeb Contractor Website Leads";
    
    var emailBody = "Hello,\n\n" +
                    "You have received a new quote request from the Tabeeb Contractor Pte Ltd website.\n\n" +
                    "Details:\n" +
                    "-----------------------------------------\n" +
                    "Name: " + (data.name || "N/A") + "\n" +
                    "Phone: " + (data.phone || "N/A") + "\n" +
                    "Email: " + (data.email || "N/A") + "\n" +
                    "Budget Range: " + (data.budget || "N/A (Home Page Form)") + "\n" +
                    "Service Requested: " + (data.service || "N/A") + "\n" +
                    "Project Scope:\n" + (data.message || "N/A") + "\n" +
                    "-----------------------------------------\n" +
                    "Submitted At: " + (data.submittedAt || new Date().toLocaleString()) + "\n\n" +
                    "This request was logged in your Google Sheets database.";
                    
    MailApp.sendEmail(emailRecipients, subject, emailBody);
    
    return ContentService.createTextOutput(JSON.stringify({ "status": "success" }))
                         .setMimeType(ContentService.MimeType.JSON);
                         
  } catch(error) {
    return ContentService.createTextOutput(JSON.stringify({ "status": "error", "message": error.toString() }))
                         .setMimeType(ContentService.MimeType.JSON);
  }
}
