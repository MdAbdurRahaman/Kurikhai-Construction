# Tabeeb Contractor Pte Ltd Website

A premium, highly responsive multi-page static website for **Tabeeb Contractor Pte Ltd**, a leading building, residential & commercial renovation, commercial reinstatement, hacking, and engineering services provider in Singapore.

## 🏢 Company Profile & Contact Info
* **Legal Name**: Tabeeb Contractor Pte Ltd
* **Office Address**: 61 Kaki Bukit Ave 1, #03-34 Shun Li Industrial Park, Singapore 417943
* **Hotline / Telephone**: [+65 8648 4883](tel:+6586484883)
* **WhatsApp Direct**: [Chat on WhatsApp (+65 8648 4883)](https://wa.me/6586484883)
* **Email**: [info@tabeebcontractor.com](mailto:info@tabeebcontractor.com)
* **Operating Hours**: Monday - Saturday: 9:00 AM - 6:00 PM (Closed on Sundays & Public Holidays)

---

## 🎨 Design & Features (Inspired by Leong Yik Standards)
* **Corporate Palette**: Deep Contractor Navy (`#0a1128`), Industrial Slate Navy (`#16224f`), Architectural Gold (`#d4af37`), and Vibrant Engineering Cyan (`#00b4d8`).
* **Top Bar**: Fast contact access across all pages displaying Singapore HQ address, operating hours, phone hotline, and instant WhatsApp chat.
* **Trust & Accreditations Bar**: BizSAFE Star safety compliance standards, BCA regulatory approvals, commercial & residential capabilities, and handover guarantees.
* **Floating WhatsApp Widget**: Persistent, pulsating WhatsApp contact button with pre-filled greeting for rapid customer conversions.
* **Interactive Map**: Embedded Google Maps location directly pinpointing Shun Li Industrial Park at 61 Kaki Bukit Ave 1, Singapore.
* **Fonts**: Google Fonts `Outfit` (headings) and `Inter` (body text).
* **Favicon**: Inline SVG brand-matching `T` lettermark.

---

## 📂 Project Structure
```
├── index.html          # Homepage (Hero, Trust bar, Features, Services overview, Lead capture, Footer)
├── services.html       # Detailed services catalog with category filter & quote requests
├── about.html          # Company background, safety commitment, core values, and workflow
├── contact.html        # Comprehensive inquiry form, Google Maps embed, and direct channels
├── style.css           # Global design system, responsive layouts, components, and animations
├── app.js              # Header scrolls, mobile menu, form validation, and Google Sheets sync
└── images/             # High-definition imagery for all contractor services
```

---

## 📊 Google Sheets & Email Integration Guide

The quote inquiry forms on **Home**, **Services**, and **Contact** pages are integrated to log submissions to a **Google Sheet** and dispatch real-time email alerts.

Follow these simple steps to deploy the Google Apps Script handler:

### Step 1: Create a Google Sheet
1. Open [Google Sheets](https://sheets.google.com/) and create a new blank spreadsheet.
2. Name your spreadsheet (e.g., `Tabeeb Contractor Website Leads`).

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
```

### Step 3: Deploy the Web App
1. Click the **Deploy** button (top right) and select **New deployment**.
2. Select type: **Web app** (click the gear icon to verify).
3. Configure the following settings:
   * **Description**: `Tabeeb Leads Handler`
   * **Execute as**: **Me** (your google email account)
   * **Who has access**: **Anyone** (allows the website to send submissions without requiring user logins)
4. Click **Deploy**. Authorize permissions when prompted.
5. Copy the generated **Web App URL** (ends with `/exec`).

### Step 4: Link to App.js
1. Open the [app.js](app.js) file.
2. Update the `GOOGLE_SCRIPT_URL` variable:
   ```javascript
   const GOOGLE_SCRIPT_URL = 'PASTE_YOUR_DEPLOYED_URL_HERE';
   ```
3. Save the file.

---

## 🚀 Automated Continuous Deployment (GitHub to cPanel)

This repository is configured with a fully automated **Continuous Deployment (CI/CD)** pipeline powered by **GitHub Actions** and cPanel's HTTPS API.

Whenever you push any updates to the `main` or `rebrand-tabeeb-contractor` branch, the live website at **[https://tabeebgroup.com](https://tabeebgroup.com)** updates automatically in **~10 seconds**.

### How to Deploy Updates:
```bash
git add .
git commit -m "Your update message"
git push origin rebrand-tabeeb-contractor
```

### Highlights:
* **Private Repository Ready:** Natively handles private GitHub repos without exposing tokens in clone URLs.
* **Firewall & Port-Resistant:** Communicates through cPanel's authenticated **HTTPS Port 2083**, completely bypassing closed FTP (port 21) and restricted SSH (port 22).
* **Automated Extraction:** Uses [unzip.php](unzip.php) to unpack new assets directly into `public_html/` and clean up the temporary archive.

📖 For the full technical breakdown, architecture diagram, and setup instructions for other projects, read the detailed **[DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)**.

---

## 📝 Blog System & Dedicated Service Social Sharing

### 1. Dedicated Service Pages (Social Media Card Previews)
Previously, sharing any service link on WhatsApp or Facebook displayed the generic website homepage title. Now, **every service has its own dedicated page and individual Open Graph metadata**:

* **Dedicated URLs**:
  * `/service/demolition-hacking` ➔ Displays *“HDB-Approved Hacking Contractor SG | Wall Hacking & Demolition”*
  * `/service/commercial-office-reinstatement` ➔ Displays *“Direct Commercial Office Reinstatement Singapore | Landlord Handover”*
  * `/service/flooring-cement-screed` ➔ Displays *“HDB Flooring & Pre-Packed Cement Screed Contractor Singapore”*
  * `/service/false-ceiling-drywall-partition` ➔ Displays *“Commercial False Ceiling & Drywall Partition Contractor Singapore”*
  * `/service/painting-plastering` ➔ Displays *“Direct Painting & Wall Plastering Contractor Singapore”*
  * `/service/plumbing-sanitary` ➔ Displays *“PUB Licensed Direct Plumbing & Sanitary Contractor Singapore”*
  * `/service/electrical-lighting` ➔ Displays *“EMA Licensed Electrical Rewiring & DB Box Upgrading SG”*
  * `/service/waterproofing-pu-injection` ➔ Displays *“Waterproofing Contractor SG | Non-Hacking PU Injection Leak Repair”*
  * `/service/metal-fabrication-grilles` ➔ Displays *“Custom Metal Fabrication, Mild Steel Gates & Window Grilles SG”*
  * `/service/home-extensions-alterations` ➔ Displays *“Landed Home Extensions, Mezzanines & Structural Alterations SG”*
* **1-Click Social Sharing**:
  * Each service card on the website and each dedicated service page includes instant **WhatsApp**, **Facebook**, **LinkedIn**, and **Copy Link** buttons.
  * On mobile phones, triggers the device's native share sheet (supporting WhatsApp, Instagram Stories/DMs, Telegram, and WeChat).

### 2. Renovation & Construction Blog
* **Public Blog (`/blog`)**: Displays articles with category filtering, instant keyword search, read time, publication date, and quick share triggers.
* **Single Post View (`/blog/[slug]`)**: Features full SEO and Open Graph card metadata, table of contents, callouts (Pro Tips & Warnings), author bio, related guides, and sticky social share bar.

### 3. Blog & User Admin Portal (`/admin`)
An intuitive, modern admin dashboard designed for easy content management and user administration:

* **Portal URL**: `https://tabeebgroup.com/admin/`
* **Default Administrator Credentials**:
  * **Username**: `admin`
  * **Password**: `TabeebAdmin@2026!`
* **Key Admin Features**:
  1. **Dashboard Overview**: Metrics for total posts, published articles, drafts, and users.
  2. **Live Social Card Simulator**: Preview in real-time exactly how any service or blog post will render in WhatsApp and Facebook chat.
  3. **Blog Management (`/admin/posts.php`)**: Create, edit, draft, publish, and delete blog articles.
  4. **Post Editor (`/admin/post-edit.php`)**:
     * Auto slug generator from title.
     * Category dropdown + custom category input.
     * Live character counter for optimal SEO meta descriptions (120–160 chars).
     * Image uploader (direct file upload to `images/blog/` or preset thumbnail selector).
     * WYSIWYG formatting toolbar (Bold, Italic, Headings H2/H3, Bullet lists, Quotes, Callout boxes).
     * Live interactive WhatsApp preview mockup updated in real-time.
  5. **User Management (`/admin/users.php`)**:
     * Add new users (Super Admin or Content Editor).
     * Edit user profiles and reset passwords with built-in strong password generator.
     * Deactivate or delete accounts with built-in safeguards (cannot delete self).
  6. **Service Social Share Links Hub (`/admin/services.php`)**:
     * Centralized dashboard with 1-click WhatsApp and Facebook share buttons for all 10 services.

