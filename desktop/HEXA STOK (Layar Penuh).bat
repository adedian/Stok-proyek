@echo off
REM =====================================================================
REM  HEXA STOK -- buka sebagai aplikasi LAYAR PENUH tanpa bingkai
REM  (tanpa address bar, tanpa tombol minimize/maximize/silang).
REM
REM  - Keluar aplikasi : tekan  Alt + F4
REM  - Butuh Google Chrome ATAU Microsoft Edge terpasang (cek otomatis).
REM  - Punya profil sendiri (%LOCALAPPDATA%\HexaStokApp) -> login HEXA STOK
REM    terpisah dari Chrome/Edge pribadi, tidak saling ganggu.
REM
REM  Kalau mau versi berjendela (masih ada tombol window), pakai file
REM  "HEXA STOK (Jendela).bat".
REM =====================================================================

set "URL=https://hexastok.hexamultienergi.com/dashboard"
set "PROFILE=%LOCALAPPDATA%\HexaStokApp"
set "FLAGS=--app=%URL% --kiosk --user-data-dir=%PROFILE% --no-first-run --no-default-browser-check --disable-features=Translate,MediaRouter"

REM --- cari Chrome ---
set "BROWSER="
for %%P in (
  "%ProgramFiles%\Google\Chrome\Application\chrome.exe"
  "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"
  "%LOCALAPPDATA%\Google\Chrome\Application\chrome.exe"
) do if exist "%%~P" set "BROWSER=%%~P"

REM --- kalau tidak ada Chrome, coba Edge ---
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
