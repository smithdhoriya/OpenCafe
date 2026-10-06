# Run this yourself: powershell -ExecutionPolicy Bypass -File scripts\download-menu-images.ps1
# Downloads 45 temporary placeholder food photos (Wikimedia Commons, free-licensed)
# directly into public/images/menu/{slug}.jpg, ready to be wired up as image_path.
# Replace any of these files later with real OpenCafe photography — same filename,
# same folder, no code changes needed.
#
# Resumable: already-downloaded files (non-empty, present on disk) are skipped, so
# this script can be re-run safely after a partial run or rate limiting.
# Wikimedia Commons rate-limits aggressively; this script adds a delay between
# requests plus automatic retry with exponential backoff on HTTP 429/5xx, honoring
# any Retry-After header Wikimedia sends.

$outDir = Join-Path $PSScriptRoot "..\public\images\menu"
New-Item -ItemType Directory -Force -Path $outDir | Out-Null

# Polite delay between successful requests (seconds). Adjust if you still see 429s.
$delaySeconds = 7
# Retry tuning for 429 / 5xx responses.
$maxRetries = 6
$baseBackoffSeconds = 10
$userAgent = "OpenCafe-MenuImageDownloader/1.0 (https://github.com/; contact: dhoriyasmith@gmail.com) PowerShell"

$items = @(
    @{ slug = "cheese-burger"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Cheeseburger_at_Louis%27_Lunch,_New_Haven.jpg" }
    @{ slug = "aloo-tikki-burger"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Aloo_Tikki_Burger.jpg" }
    @{ slug = "mayonnaise-burger"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Gourmet_Burger_Kitchen_hamburger.jpg" }
    @{ slug = "schezwan-burger"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Fried_onion_burger.jpg" }
    @{ slug = "garlic-cheese-burger"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Cheeseburger_maison.jpg" }
    @{ slug = "tandoori-pizza"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Tandoori_Paneer_Pizza_from_India.jpg" }
    @{ slug = "american-garden-pizza"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Bengal_Style_Pizza_-_Kolkata.jpg" }
    @{ slug = "margherita-pizza"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Eq_it-na_pizza-margherita_sep2005_sml.jpg" }
    @{ slug = "7-cheese-pizza"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Cheese_Pizza_7.jpg" }
    @{ slug = "italian-pizza"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Cheese_Pizza_1.0.jpg" }
    @{ slug = "jain-italian"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Puneri_Pizza.png" }
    @{ slug = "mexican-pizza"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Supreme_pizza.jpg" }
    @{ slug = "jain-frankie"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Roll.Veg.jpg" }
    @{ slug = "aloo-frankie"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Aloo_Corn_Frankie_Roll.jpg" }
    @{ slug = "noodles-frankie"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Kaati_roll.jpg" }
    @{ slug = "paneer-frankie"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Paneer_kathi_roll_homemade.jpg" }
    @{ slug = "tea"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/An_Indian_cup_of_tea.jpg" }
    @{ slug = "coffee-small"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Espresso_cup.jpg" }
    @{ slug = "hot-coffee-half"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Coffee_cup_with_filter.JPG" }
    @{ slug = "hot-coffee-full"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Cappuccino_mug_and_saucer.jpg" }
    @{ slug = "cold-coffee"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Cold_Coffee_at_De_Pauls,_Janpath,_near_Connaught_Place,_New_Delhi.jpg" }
    @{ slug = "chaas"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Masala_Buttermilk.JPG" }
    @{ slug = "plain-sandwich"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/ClassicSandwich_HomeMade_Mumbai.jpg" }
    @{ slug = "veg-cheese-sandwich"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Cheese_sandwich.jpg" }
    @{ slug = "grill-sandwich"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Grilled_Sandwich.jpg" }
    @{ slug = "cheese-grill"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Cheez_sandwich.jpg" }
    @{ slug = "tandoori-sandwich"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/IndianSpiceSandwich_ShaktiSandwich_ahmedabad.jpg" }
    @{ slug = "paneer-cheese-grill"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Paneer_Tikka_Sandwich_at_New_Diamond_Agra_Stall.jpg" }
    @{ slug = "masala-toast-sandwich"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/MumbaiMasalaToast_C2Cafe_Mumbai.jpg" }
    @{ slug = "veg-toast-sandwich"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/VegToast_HomeMade_Mumbai.jpg" }
    @{ slug = "masala-noodle-toast-sandwich"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Shaolin_-_the_noodle_sandwich_!.jpg" }
    @{ slug = "french-fries"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/French_Fries.jpg" }
    @{ slug = "mayo-fries"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/French_fries_with_mayonnaise.jpg" }
    @{ slug = "peri-peri-fries"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Chilli_cheese_fries.jpg" }
    @{ slug = "cheesy-fries"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/French_Fries_with_Cheese.jpg" }
    @{ slug = "special-fries"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Cup_of_Large_Fries_Santa_Cruz_Beach_Boardwalk.jpg" }
    @{ slug = "vada-pav"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Maharashtrian_Vada_Pav.JPG" }
    @{ slug = "special-vada-pav"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Vada_Pav-Indian_street_food.JPG" }
    @{ slug = "mayo-vada-pav"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Puneri_Vada_Pav.jpg" }
    @{ slug = "schezwan-vada-pav"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Vada_pav_and_Misal_pav.jpg" }
    @{ slug = "garlic-vada-pav"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Vada_Pav_with_Fresh_green_coriander_and_mint_Chutney_and_Garlic_chutney.jpg" }
    @{ slug = "cheese-garlic-vada-pav"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Mini_Vada_Pav.jpg" }
    @{ slug = "cheese-vada-pav"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Mumbai_Cheese_Vada_Pav.JPG" }
    @{ slug = "grill-vada-pav"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/Crunchy_Crispy_Vada_pav.jpg" }
    @{ slug = "tawa-butter-vada-pav"; url = "https://commons.wikimedia.org/wiki/Special:FilePath/ButterGrillVadaPaav_Surbhi_Mumbai.jpg" }
)

function Invoke-ImageDownload {
    param(
        [string]$Url,
        [string]$Dest
    )

    for ($attempt = 0; $attempt -le $maxRetries; $attempt++) {
        try {
            Invoke-WebRequest -Uri $Url -OutFile $Dest -UserAgent $userAgent -MaximumRedirection 5 -ErrorAction Stop
            return @{ Success = $true; Error = $null }
        } catch {
            $resp = $_.Exception.Response
            $statusCode = $null
            if ($resp -and $resp.StatusCode) {
                $statusCode = [int]$resp.StatusCode
            }

            $isRetryable = ($statusCode -eq 429) -or ($statusCode -ge 500 -and $statusCode -le 599)

            # Clean up any partial/empty file left behind by a failed request.
            if (Test-Path $Dest) {
                Remove-Item $Dest -Force -ErrorAction SilentlyContinue
            }

            if (-not $isRetryable -or $attempt -eq $maxRetries) {
                return @{ Success = $false; Error = $_.Exception.Message }
            }

            # Respect Retry-After if Wikimedia sent one; otherwise exponential backoff.
            $waitSeconds = $baseBackoffSeconds * [math]::Pow(2, $attempt)
            if ($resp -and $resp.Headers -and $resp.Headers["Retry-After"]) {
                $retryAfter = $resp.Headers["Retry-After"]
                $parsedSeconds = 0
                if ([int]::TryParse($retryAfter, [ref]$parsedSeconds)) {
                    $waitSeconds = $parsedSeconds
                }
            }

            Write-Host "RETRY $($statusCode) on attempt $($attempt + 1)/$($maxRetries + 1), waiting $([int]$waitSeconds)s..." -ForegroundColor Yellow
            Start-Sleep -Seconds $waitSeconds
        }
    }
}

$ok = 0
$skipped = 0
$failed = @()

foreach ($item in $items) {
    $dest = Join-Path $outDir "$($item.slug).jpg"

    if ((Test-Path $dest) -and ((Get-Item $dest).Length -gt 0)) {
        Write-Host "SKIP $($item.slug) (already downloaded)"
        $skipped++
        continue
    }

    $result = Invoke-ImageDownload -Url $item.url -Dest $dest

    if ($result.Success) {
        Write-Host "OK   $($item.slug)"
        $ok++
    } else {
        Write-Host "FAIL $($item.slug): $($result.Error)" -ForegroundColor Red
        $failed += $item.slug
    }

    # Polite delay between requests so we don't trip Wikimedia's rate limiter again.
    Start-Sleep -Seconds $delaySeconds
}

Write-Host ""
Write-Host "Downloaded this run: $ok, skipped (already had): $skipped, total target: $($items.Count)"
if ($failed.Count -gt 0) {
    Write-Host "Failed: $($failed -join ', ')" -ForegroundColor Yellow
    Write-Host "Re-run this script to retry just the failed ones (successful files are skipped automatically)." -ForegroundColor Yellow
}
