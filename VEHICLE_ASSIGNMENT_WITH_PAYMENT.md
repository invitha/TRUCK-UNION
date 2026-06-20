# Vehicle Assignment with Payment Tracking - Complete Guide

## Overview
Enhanced the dashboard.php to include vehicle assignment functionality with comprehensive payment tracking. Internal team can now assign vehicles to shipments (AL numbers) and track payment status consistently.

## Features Added

### 1. **Vehicle Assignment Modal**
- Click "📋 Assign" button on any vehicle in the dashboard
- Modal popup with complete assignment form
- Shows selected vehicle details (number, name, driver, vendor)

### 2. **Payment Tracking System**
Four payment statuses available:
- **Unpaid**: No payment received yet
- **Advance Paid**: Partial advance payment received
- **Partially Paid**: Some payment received (not full)
- **Fully Paid**: Complete payment received

### 3. **Payment Calculation**
- Enter total payment amount
- Enter advance amount (if applicable)
- System automatically calculates remaining amount
- Real-time display of remaining balance

### 4. **Assignment Details**
- AL Number (Airway Bill/Logistics Number)
- Pickup Location
- Delivery Location
- Expected Completion Date
- Assigned By (Internal team member name)
- Notes (Additional instructions)

## Database Setup

### Step 1: Add Payment Columns to Database

Run this SQL on your database:

```sql
ALTER TABLE fleet_assignments 
ADD COLUMN IF NOT EXISTS payment_status ENUM('unpaid', 'advance_paid', 'partially_paid', 'fully_paid') DEFAULT 'unpaid' AFTER notes,
ADD COLUMN IF NOT EXISTS payment_amount DECIMAL(10,2) DEFAULT 0.00 AFTER payment_status,
ADD COLUMN IF NOT EXISTS advance_amount DECIMAL(10,2) DEFAULT 0.00 AFTER payment_amount,
ADD COLUMN IF NOT EXISTS remaining_amount DECIMAL(10,2) DEFAULT 0.00 AFTER advance_amount,
ADD COLUMN IF NOT EXISTS payment_date DATETIME NULL AFTER remaining_amount,
ADD COLUMN IF NOT EXISTS payment_notes TEXT NULL AFTER payment_date;
```

**OR** Upload and run the SQL file:
```bash
# Upload this file to your server
vendor_app/server_php/add_payment_columns.sql

# Then run it via phpMyAdmin or command line:
mysql -u royaldxd_user -p royaldxd_abra_crm < add_payment_columns.sql
```

## How to Use

### For Internal Team:

1. **Open Dashboard**
   - Navigate to `dashboard.php`
   - View all vehicles with their status

2. **Assign a Vehicle**
   - Find the vehicle you want to assign
   - Click the "📋 Assign" button in the Actions column
   - Assignment modal will open

3. **Fill Assignment Form**
   - **AL Number**: Enter the shipment/logistics number (e.g., AL123456)
   - **Pickup Location**: Enter full pickup address
   - **Delivery Location**: Enter full delivery address
   - **Expected Completion Date**: Select target completion date
   - **Assigned By**: Enter your name (optional)

4. **Set Payment Details**
   - **Payment Status**: Select from dropdown
     - Unpaid: No payment yet
     - Advance Paid: Advance given
     - Partially Paid: Some payment done
     - Fully Paid: Complete payment
   
   - **Total Payment Amount**: Enter total agreed amount (₹)
   
   - **Advance Amount**: (Shows only for Advance/Partial)
     - Enter advance amount paid
     - System shows remaining amount automatically
   
   - **Notes**: Add any special instructions

5. **Submit Assignment**
   - Click "✅ Assign Vehicle"
   - Success message will appear
   - Assignment is saved to database

### For Vendors:

Vendors can see their assigned vehicles in:
- Vendor app's "Assigned Fleets" screen
- Shows AL number, locations, payment status
- Can track shipment progress

## Payment Status Flow

```
Unpaid → Advance Paid → Partially Paid → Fully Paid
```

### Example Scenarios:

**Scenario 1: Advance Payment**
- Total Amount: ₹50,000
- Status: Advance Paid
- Advance: ₹15,000
- Remaining: ₹35,000 (auto-calculated)

**Scenario 2: Partial Payment**
- Total Amount: ₹50,000
- Status: Partially Paid
- Advance: ₹30,000
- Remaining: ₹20,000

**Scenario 3: Full Payment**
- Total Amount: ₹50,000
- Status: Fully Paid
- Advance: ₹0 (or ₹50,000)
- Remaining: ₹0

## Files Modified

1. **dashboard.php**
   - Added assignment form handling
   - Added assignment modal HTML
   - Added payment calculation JavaScript
   - Added "Assign" button in vehicle table
   - Added success/error message display

2. **New Files Created**
   - `server_php/add_payment_columns.sql` - Database migration
   - `VEHICLE_ASSIGNMENT_WITH_PAYMENT.md` - This documentation

## Technical Details

### Form Validation
- AL Number: Required, alphanumeric only
- Locations: Required text fields
- Date: Required, must be today or future
- Payment Amount: Required, decimal number
- Advance Amount: Optional, decimal number

### Security Features
- SQL injection protection (prepared statements)
- XSS protection (htmlspecialchars)
- Form resubmission prevention (redirect after POST)
- Input validation on both client and server side

### Database Structure

**fleet_assignments table columns:**
```
- id (primary key)
- al_number (varchar)
- vehicle_id (int)
- vendor_firebase_uid (varchar)
- vehicle_number (varchar)
- vehicle_name (varchar)
- driver_name (varchar)
- assigned_by (varchar)
- pickup_location (text)
- delivery_location (text)
- expected_completion_date (date)
- status (enum: active, completed, cancelled)
- notes (text)
- payment_status (enum: unpaid, advance_paid, partially_paid, fully_paid) ← NEW
- payment_amount (decimal) ← NEW
- advance_amount (decimal) ← NEW
- remaining_amount (decimal) ← NEW
- payment_date (datetime) ← NEW
- payment_notes (text) ← NEW
- created_at (timestamp)
- updated_at (timestamp)
```

## Consistency with Vendor App

The payment tracking is consistent with vendor app:
- Same payment statuses
- Same calculation logic
- Vendors see same information in their app
- Real-time sync via database

## Testing Checklist

- [ ] SQL migration runs successfully
- [ ] Dashboard loads without errors
- [ ] Assign button appears on all vehicles
- [ ] Modal opens when clicking Assign
- [ ] Vehicle info displays correctly in modal
- [ ] All form fields are editable
- [ ] Payment status dropdown works
- [ ] Advance amount field shows/hides correctly
- [ ] Remaining amount calculates correctly
- [ ] Form submits successfully
- [ ] Success message appears after submission
- [ ] Assignment appears in fleet_assignments table
- [ ] Vendor can see assignment in their app

## Troubleshooting

### Issue: Payment columns not working
**Solution**: Run the SQL migration file to add payment columns

### Issue: Modal not opening
**Solution**: Check browser console for JavaScript errors

### Issue: Form not submitting
**Solution**: Check all required fields are filled

### Issue: Remaining amount not calculating
**Solution**: Ensure payment_amount and advance_amount have valid numbers

## Future Enhancements

Possible additions:
- Edit existing assignments
- Cancel assignments
- Payment history tracking
- Multiple payment installments
- Payment receipt upload
- SMS/Email notifications to vendor
- Payment reminder system
- Analytics dashboard for payments

## Support

For issues or questions:
1. Check this documentation
2. Verify database migration ran successfully
3. Check browser console for errors
4. Check PHP error logs
5. Verify database connection settings

---

**Version**: 1.0  
**Last Updated**: <?php echo date('Y-m-d'); ?>  
**Author**: Internal Team
