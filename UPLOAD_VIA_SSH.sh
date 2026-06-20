#!/bin/bash
# Upload get_notifications.php to server via SSH
# Run this script from your LOCAL machine

echo "📤 Uploading get_notifications.php to server..."

# Check if file exists locally
if [ ! -f "vendor_app/server_php/api1_vendor/get_notifications.php" ]; then
    echo "❌ Error: get_notifications.php not found locally"
    exit 1
fi

# Upload file (replace 'your-server-ip' with actual IP)
scp vendor_app/server_php/api1_vendor/get_notifications.php root@your-server-ip:/home/royaldxd/crm.abra-logistic.com/api1/vendor/

echo "✅ File uploaded!"
echo ""
echo "Now run these commands on the server:"
echo "cd /home/royaldxd/crm.abra-logistic.com/api1/vendor/"
echo "chmod 644 get_notifications.php"
echo "chown apache:apache get_notifications.php"
echo ""
echo "Or if apache user doesn't exist, try:"
echo "chown nobody:nobody get_notifications.php"
