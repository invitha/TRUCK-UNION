# UPLOAD THESE FILES TO SERVER NOW

## Server Path
Upload to: `/home/royaldxd/crm.abra-logistic.com/api1/vendor/`

## Files to Upload (in order)

### 1. Core API Files (REQUIRED)
- ✅ `submit_kyc.php` - KYC submission handler
- ✅ `get_kyc_status.php` - Get KYC status
- ✅ `check_kyc_exists.php` - Check if KYC exists
- ✅ `upload_kyc_documents.php` - Document upload handler
- ✅ `get_notifications.php` - Get notifications
- ✅ `mark_notification_read.php` - Mark notification as read

### 2. Test Files (Optional but helpful)
- `test_simple_connection.php` - Test database connection
- `test_upload_simple.php` - Test upload endpoint

## What's Fixed in These Files

1. ✅ Database connection using direct credentials (like abra_app)
2. ✅ CORS headers with OPTIONS handling
3. ✅ Proper error handling
4. ✅ Database: `royaldxd_abra`
5. ✅ Username: `royaldxd_abra`
6. ✅ Password: `meg_layout312`

## After Upload

1. Test the connection: `https://crm.abra-logistic.com/api1/vendor/test_simple_connection.php`
2. Try KYC submission again from the app
3. Check browser console for any remaining errors

## Important Notes

- All files use the same database credentials as abra_app
- CORS is properly configured for cross-origin requests
- OPTIONS preflight requests are handled
- Error logging is enabled but display_errors is off for security
