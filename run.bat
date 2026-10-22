@echo off
title Muse2API Gateway
cd /d "%~dp0"
echo ====================================================
echo Starting Muse2API Gateway on http://127.0.0.1:18610
echo Admin Dashboard: http://127.0.0.1:18610/dashboard
echo ====================================================
python -m muse2api
pause
