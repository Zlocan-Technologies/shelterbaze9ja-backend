# Test the property update endpoint

# Example API call to test facilities update:
curl -X PUT "http://your-app-url/api/properties/{property_id}" \
  -H "Authorization: Bearer your-jwt-token" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Updated Property Title",
    "facilities": ["WiFi", "Parking", "Swimming Pool", "Gym", "Security"]
  }'

# Or using a form request:
curl -X PUT "http://your-app-url/api/properties/{property_id}" \
  -H "Authorization: Bearer your-jwt-token" \
  -F "title=Updated Property Title" \
  -F "facilities[]=WiFi" \
  -F "facilities[]=Parking" \
  -F "facilities[]=Swimming Pool"

# Check the logs after making the request:
tail -f storage/logs/laravel.log