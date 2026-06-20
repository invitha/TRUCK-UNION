# KYC Process: Individual vs Business Vendors

## ✅ Current Implementation Status

Your KYC system **already supports** distinguishing between individual and business vendors with proper data collection!

## 📋 Data Collection by Account Type

### **Individual Vendors** (Personal Use)
Collects the following information:
- ✅ **Full Name** (Personal name)
- ✅ **Email Address**
- ✅ **Phone Number**
- ✅ Aadhaar Number
- ✅ PAN Number
- ✅ Bank Account Details
- ✅ Required Documents (Aadhaar, PAN, Photo, Bank Account Photo)

### **Business Vendors** (Company Use)
Collects everything from Individual PLUS:
- ✅ **Company/Business Name** (Required for business)
- ✅ **GST Number** (Required for business)
- ✅ **Business Address** (Required for business)
- ✅ Additional Documents (GST Certificate, Address Proof)

## 🎯 How It Works

### 1. **Account Type Selection**
When vendors open the KYC screen, they see two options:
- **Individual** - For personal use
- **Business** - For company use

### 2. **Dynamic Form Fields**
The form automatically adjusts based on selection:

**Individual Account:**
- Company Name: Optional
- GST Number: Optional
- Business Address: Optional
- GST Certificate: Optional
- Address Proof: Optional

**Business Account:**
- Company Name: **Required** ⚠️
- GST Number: **Required** ⚠️
- Business Address: **Required** ⚠️
- GST Certificate: **Required** ⚠️
- Address Proof: **Required** ⚠️

### 3. **Validation**
The system validates:
- Individual accounts can submit without business details
- Business accounts MUST provide company name, GST, and business documents
- All accounts must provide personal details (name, email, phone)

## 📊 Database Structure

The `vendor_kyc` table stores:

```sql
-- Account Type
account_type ENUM('individual', 'business') DEFAULT 'individual'

-- Always Required
name VARCHAR(255) NOT NULL
email VARCHAR(255) NOT NULL
phone VARCHAR(20) NOT NULL

-- Optional for Individual, Required for Business
company_name VARCHAR(255) DEFAULT NULL
gst_number VARCHAR(15) DEFAULT NULL
address TEXT DEFAULT NULL
```

## 🔄 User Flow

### Individual Vendor Flow:
1. Select "Individual" account type
2. Fill personal details (name, email, phone)
3. Fill Aadhaar & PAN
4. Fill bank account details
5. Upload required documents
6. Submit ✅

### Business Vendor Flow:
1. Select "Business" account type
2. Fill personal details (name, email, phone)
3. Fill Aadhaar & PAN
4. **Fill company name** ⚠️
5. **Fill GST number** ⚠️
6. **Fill business address** ⚠️
7. Fill bank account details
8. Upload required documents
9. **Upload GST certificate** ⚠️
10. **Upload address proof** ⚠️
11. Submit ✅

## 🎨 UI Features

### Visual Indicators:
- **Individual**: Blue color, Person icon
- **Business**: Green color, Business icon

### Smart Sections:
- "Business Details (Optional)" for individual accounts
- "Business Details (Required)" for business accounts

### Upload Status:
- Shows which documents are required vs optional
- Changes based on account type selection
- Real-time validation before submission

## 📱 API Endpoints

### Submit KYC
**Endpoint:** `POST /api1/vendor/upload_kyc_documents.php`

**Payload includes:**
```json
{
  "firebase_uid": "xxx",
  "account_type": "individual" | "business",
  "name": "Full Name",
  "email": "email@example.com",
  "phone": "9876543210",
  "company_name": "Company Name (if business)",
  "gst_number": "GST123... (if business)",
  "address": "Business Address (if business)",
  ...
}
```

### Get KYC Status
**Endpoint:** `POST /api1/vendor/get_kyc_status.php`

**Returns:**
```json
{
  "status": "success",
  "kyc_status": "submitted|verified|rejected",
  "account_type": "individual|business",
  "name": "...",
  "email": "...",
  "phone": "...",
  "company_name": "..." // null for individual
}
```

## ✨ Key Features

1. **Automatic Field Validation**
   - Business accounts cannot submit without company details
   - Individual accounts can skip business fields

2. **Document Requirements**
   - Dynamically adjusts based on account type
   - Clear visual indicators for required vs optional

3. **Data Integrity**
   - Unique constraints on Aadhaar, PAN, Bank Account
   - Prevents duplicate registrations

4. **User Experience**
   - Clear labeling with asterisks (*) for required fields
   - Collapsible sections for better organization
   - Real-time upload status tracking

## 🎯 Summary

**Your KYC system is already complete!** It properly:
- ✅ Distinguishes between individual and business vendors
- ✅ Collects name, email, phone for all vendors
- ✅ Collects company name for business vendors
- ✅ Validates required fields based on account type
- ✅ Stores account type in database
- ✅ Shows appropriate UI based on selection

No changes needed - the system works exactly as you described! 🎉
