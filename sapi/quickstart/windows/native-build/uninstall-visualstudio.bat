@echo off

echo %~dp0
cd %~dp0
cd ..\..\..\..\

set __PROJECT__=%cd%
echo %cd%

VisualStudioSetup.exe export 	--passive  --force --norestart


VisualStudioSetup.exe uninstall	--passive  --force --norestart ^
--remove Microsoft.VisualStudio.Component.Windows11SDK.22000   ^
--remove Microsoft.VisualStudio.Workload.NativeDesktop ^
--remove Microsoft.VisualStudio.Component.VC.CLI.Support ^
--remove Microsoft.VisualStudio.Component.VC.Redist.MSM

