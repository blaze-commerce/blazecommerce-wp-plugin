# Simple test for redeploy API HTTP 400 error
Write-Host "=== Byron Bay Candles Redeploy API Test ===" -ForegroundColor Green

$readonlyApiKey = "KSZseBDzRYq476OgkcYDxTbZomC8SrWb"
$storeId = "74"
$redeployApiUrl = "https://my-wooless-admin-portal.vercel.app/api/deployments"

# Create authentication header as used by the plugin
$authString = $readonlyApiKey + ":" + $storeId
$base64Auth = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($authString))

$headers = @{
    "x-wooless-secret-token" = $base64Auth
}

Write-Host "Testing POST request to deployment API..."
Write-Host "URL: $redeployApiUrl"
Write-Host "Auth Header: x-wooless-secret-token = $base64Auth"
Write-Host ""

try {
    $response = Invoke-WebRequest -Uri $redeployApiUrl -Method Post -Headers $headers -TimeoutSec 30 -ErrorAction Stop
    Write-Host "SUCCESS - HTTP $($response.StatusCode)" -ForegroundColor Green
    Write-Host "Response: $($response.Content)"
} catch {
    $statusCode = $_.Exception.Response.StatusCode.value__
    Write-Host "FAILED - HTTP $statusCode" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)"
    
    if ($statusCode -eq 400) {
        Write-Host "This matches the reported HTTP 400 error!" -ForegroundColor Yellow
        
        # Try to get error details
        try {
            $errorStream = $_.Exception.Response.GetResponseStream()
            $reader = New-Object System.IO.StreamReader($errorStream)
            $errorBody = $reader.ReadToEnd()
            Write-Host "Error Details: $errorBody" -ForegroundColor Yellow
        } catch {
            Write-Host "Could not read error response details" -ForegroundColor Yellow
        }
    }
}

Write-Host ""
Write-Host "ANALYSIS:" -ForegroundColor Cyan
Write-Host "- HTTP 400 typically means 'Bad Request'" -ForegroundColor White
Write-Host "- This could be due to:" -ForegroundColor White
Write-Host "  1. Invalid API key for deployment operations" -ForegroundColor White
Write-Host "  2. Readonly key doesn't have deployment permissions" -ForegroundColor White
Write-Host "  3. Missing required parameters in request" -ForegroundColor White
Write-Host "  4. Wrong authentication format" -ForegroundColor White
