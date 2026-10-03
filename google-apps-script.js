/**
 * Google Apps Script Web App for Tabeeb Contractor Pte Ltd.
 * 
 * Instructions:
 * 1. Open your target Google Sheet.
 * 2. Go to Extensions > Apps Script.
 * 3. Replace all existing code with this script.
 * 4. Click Save, then click "Deploy" > "Manage deployments" > Edit (pencil icon) > choose "New version" > Deploy.
 *    (Or if setting up for the first time: Deploy > New deployment > Web app > Execute as Me > Who has access: Anyone).
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
    
    // Send email alert to both recipients
    var emailRecipients = "infotabeebcontractor@gmail.com, abdurrahaman1a1@gmail.com";
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
