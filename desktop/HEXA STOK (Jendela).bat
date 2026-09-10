@echo off
REM =====================================================================
REM  HEXA STOK -- buka sebagai aplikasi BERJENDELA (tanpa address bar,
REM  tapi masih ada tombol minimize/maximize/silang bawaan Windows).
REM
REM  Lebih nyaman untuk pemakaian sehari-hari (bisa di-minimize, pindah
REM  layar, dsb). Untuk benar-benar tanpa bingkai pakai
REM  "HEXA STOK (Layar Penuh).bat".
REM =====================================================================

set "URL=https://hexastok.hexamultienergi.com/dashboard"
set "PROFILE=%LOCALAPPDATA%\HexaStokApp"
set "FLAGS=--app=%URL% --user-data-dir=%PROFILE% --start-maximized --no-first-run --no-default-browser-check --disable-features=Translate,MediaRouter"

set "BROWSER="
for %%P in (
  "%ProgramFiles%\Google\Chrome\Application\chrome.exe"
  "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"
  "%LOCALAPPDATA%\Google\Chrome\Application\chrome.exe"
) do if exist "%%~P" set "BROWSER=%%~P"

if not defined BROWSER for %%P in (
  "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe"
  "%ProgramFiles%\Microsoft\Edge\Application\msedge.exe"
) do if exist "%%~P" set "BROWSER=%%~P"

if not defined BROWSER (
  echo.
  echo   Google Chrome atau Microsoft Edge tidak ditemukan.
  echo   Install salah satu lalu jalankan file ini lagi.
  echo.
  pause
  exit /b 1
)

start "" "%BROWSER%" %FLAGS%
exit /b 0
