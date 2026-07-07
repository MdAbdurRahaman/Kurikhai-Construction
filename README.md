# Kurikhai Construction Pte Ltd Website

A premium, highly responsive multi-page static website for **Kurikhai Construction Pte Ltd**, a leading building, renovation, hacking, and structural engineering services provider in Singapore.

## 🎨 Design & Theme
* **Colors**: Premium Midnight Navy (`#0b132b` / `#1c2541`) as primary dark, and Vibrant Electric Orange (`#ff6b00` / `#e65f00`) as high-contrast action accent.
* **Fonts**: Outfit (headings) and Inter (body text) imported from Google Fonts.
* **Animations**: Custom slide-up fade effects utilizing `IntersectionObserver`.
* **Favicon**: Integrated brand-matching inline SVG icon (`K` logo) for instant tab branding.

## 📂 Project Structure
```
├── index.html          # Homepage (Hero, Services highlights, About teaser, Quick Quote form)
├── services.html       # Services page (10 detailed service listings + interactive category filter)
├── about.html          # About company profile page (Core values, detailed project workflow)
├── contact.html        # Contact page (HQ/hotline details, full inquiry form with budget range)
├── style.css           # Global stylesheet and custom styling rules
├── app.js              # Header scrolls, mobile navbar, form validation, and spreadsheet sync
├── website.zip         # ZIP archive ready for cPanel uploads
└── images/             # Folder containing high-definition service graphics
```

---

## 📊 Google Sheets & Email Integration Guide

The quote inquiry forms on **Home** and **Contact** pages are integrated to save submissions to a **Google Sheet** and dispatch email notifications directly to **infovvpengineering@gmail.com** mentioning **Kurikhai Construction Pte Ltd**. 

Follow these simple steps to deploy the handler:

### Step 1: Create a Google Sheet
1. Open [Google Sheets](https://sheets.google.com/) and create a new blank spreadsheet.
2. Name your spreadsheet (e.g., `Kurikhai Website Leads`).

### Step 2: Attach Google Apps Script
1. Inside your new spreadsheet, go to **Extensions** > **Apps Script**.
2. Delete any default code in the editor and paste the script below:

```javascript
function doPost(e) {
  try {
    // Parse form input payload
    var data = JSON.parse(e.postData.contents);
    
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
    
    // Setup headers on the first row if sheet is empty
    if (sheet.getLastRow() === 0) {
      sheet.appendRow(["Timestamp", "Name", "Phone", "Email", "Budget", "Service Requested", "Message", "Consent Acknowledged", "Company"]);
    }
    
    // Log details as a new row
    sheet.appendRow([
      data.submittedAt || new Date().toLocaleString(),
      data.name || "",
      data.phone || "",
      data.email || "",
      data.budget || "N/A (Home Page Form)",
      data.service || "",
      data.message || "",
      data.consent ? "Yes" : "N/A",
      data.companyName || "Kurikhai Construction Pte Ltd"
    ]);
    
    // Send email alert
    var emailRecipient = "infovvpengineering@gmail.com";
    var subject = "New Quote Request - " + (data.companyName || "Kurikhai Construction Pte Ltd");
    
    var emailBody = "Hello,\n\n" +
                    "You have received a new quote request from the Kurikhai Construction Pte Ltd website.\n\n" +
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
                    
    MailApp.sendEmail(emailRecipient, subject, emailBody);
    
    return ContentService.createTextOutput(JSON.stringify({ "status": "success" }))
                         .setMimeType(ContentService.MimeType.JSON);
                         
  } catch(error) {
    return ContentService.createTextOutput(JSON.stringify({ "status": "error", "message": error.toString() }))
                         .setMimeType(ContentService.MimeType.JSON);
  }
}
```

### Step 3: Deploy the Web App
1. Click the **Deploy** button (top right) and select **New deployment**.
2. Select type: **Web app** (click the gear icon to verify).
3. Configure the following settings:
   * **Description**: `Kurikhai Leads Handler`
   * **Execute as**: **Me** (your google email account)
   * **Who has access**: **Anyone** (this allows the website to send submissions without user authentication)
4. Click **Deploy**. Authorize the permissions when prompted (Google may warn you the app is unverified; click *Advanced* > *Go to Untitled Project (unsafe)* to approve).
5. Copy the **Web App URL** generated (ends with `/exec`).

### Step 4: Link to App.js
1. Open the [app.js](app.js) file.
2. Locate the variable at the top:
   `const GOOGLE_SCRIPT_URL = 'YOUR_GOOGLE_APPS_SCRIPT_URL_HERE';`
3. Replace `'YOUR_GOOGLE_APPS_SCRIPT_URL_HERE'` with your copied deployment URL. Save the file.
