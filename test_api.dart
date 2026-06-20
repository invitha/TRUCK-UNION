import 'dart:convert';
import 'package:http/http.dart' as http;

void main() async {
  var url = Uri.parse('https://crm.abra-logistic.com/api1/vendor/get_notifications.php');
  var response = await http.post(url, body: {
    'firebase_uid': 'qHa4BnKV1wSanQHE1QssUyF4wdH3',
    'timestamp': '0'
  });
  print('Status: ${response.statusCode}');
  print('Body: ${response.body}');
}
