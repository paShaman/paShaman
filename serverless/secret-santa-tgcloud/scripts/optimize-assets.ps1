param(
  [string]$Root = (Split-Path -Parent $PSScriptRoot)
)

Add-Type -AssemblyName System.Drawing

function Resize-Image {
  param(
    [string]$Source,
    [string]$Destination,
    [int]$Width,
    [int]$Height,
    [string]$Format = 'jpg',
    [int]$Quality = 86,
    [int]$CropTop = 0
  )
  $image = [System.Drawing.Image]::FromFile($Source)
  $bitmap = New-Object System.Drawing.Bitmap $Width, $Height
  $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
  $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
  $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
  $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
  $destRect = New-Object System.Drawing.Rectangle 0, 0, $Width, $Height
  $sourceHeight = $image.Height - $CropTop
  $srcRect = New-Object System.Drawing.Rectangle 0, $CropTop, $image.Width, $sourceHeight
  $graphics.DrawImage($image, $destRect, $srcRect, [System.Drawing.GraphicsUnit]::Pixel)
  $destinationPath = Join-Path $Root $Destination
  $directory = Split-Path -Parent $destinationPath
  if (-not (Test-Path -LiteralPath $directory)) { New-Item -ItemType Directory -Force -Path $directory | Out-Null }
  if ($Format -eq 'png') {
    $bitmap.Save($destinationPath, [System.Drawing.Imaging.ImageFormat]::Png)
  } else {
    $codec = [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() | Where-Object { $_.MimeType -eq 'image/jpeg' }
    $parameters = New-Object System.Drawing.Imaging.EncoderParameters 1
    $parameters.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter ([System.Drawing.Imaging.Encoder]::Quality), $Quality
    $bitmap.Save($destinationPath, $codec, $parameters)
  }
  $graphics.Dispose()
  $bitmap.Dispose()
  $image.Dispose()
}

$preview = Join-Path $Root 'docs/preview.jpg'
$avatar = Join-Path $Root 'docs/avatar.png'

Resize-Image -Source $preview -Destination 'miniapp/src/assets/hero-1280.jpg' -Width 1280 -Height 832 -Format jpg -Quality 86 -CropTop 0
Resize-Image -Source $preview -Destination 'miniapp/src/assets/hero-640.jpg' -Width 640 -Height 416 -Format jpg -Quality 84 -CropTop 0
Resize-Image -Source $avatar -Destination 'miniapp/src/assets/tree-512.jpg' -Width 512 -Height 512 -Format jpg -Quality 88
Resize-Image -Source $avatar -Destination 'miniapp/src/assets/tree-192.png' -Width 192 -Height 192 -Format png
Resize-Image -Source $avatar -Destination 'miniapp/public/avatar-640.jpg' -Width 640 -Height 640 -Format jpg -Quality 88

Write-Output 'assets generated'




