param(
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'
$scriptPath = Join-Path $PSScriptRoot 'create_admin.php'
if (-not (Test-Path -LiteralPath $PhpPath)) {
    throw "PHP CLI was not found at '$PhpPath'. Pass the path to php.exe as an argument."
}

$email = (Read-Host 'Administrator email').Trim()
$firstName = (Read-Host 'First name').Trim()
$middleName = (Read-Host 'Middle name (optional)').Trim()
$lastName = (Read-Host 'Last name').Trim()
$employeeNo = (Read-Host 'Employee number (optional)').Trim()
$position = (Read-Host 'Position title (optional)').Trim()
$securePassword = Read-Host 'New unique password (12+ characters)' -AsSecureString
$secureConfirmation = Read-Host 'Confirm password' -AsSecureString
$passwordPointer = [IntPtr]::Zero
$confirmationPointer = [IntPtr]::Zero
$process = $null

try {
    $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
    $confirmationPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureConfirmation)
    $password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
    $confirmation = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($confirmationPointer)
    if ($password -cne $confirmation) {
        throw 'The passwords do not match.'
    }

    $payload = @{
        email = $email
        first_name = $firstName
        middle_name = $middleName
        last_name = $lastName
        employee_no = $employeeNo
        position_title = $position
        password = $password
    } | ConvertTo-Json -Compress

    $startInfo = New-Object System.Diagnostics.ProcessStartInfo
    $startInfo.FileName = $PhpPath
    $startInfo.Arguments = '"' + $scriptPath + '"'
    $startInfo.WorkingDirectory = $PSScriptRoot
    $startInfo.UseShellExecute = $false
    $startInfo.CreateNoWindow = $true
    $startInfo.RedirectStandardInput = $true
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true

    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $startInfo
    if (-not $process.Start()) {
        throw 'Could not start PHP CLI.'
    }

    $process.StandardInput.WriteLine($payload)
    $process.StandardInput.Close()
    $stdout = $process.StandardOutput.ReadToEnd()
    $stderr = $process.StandardError.ReadToEnd()
    $process.WaitForExit()

    if ($stdout) { [Console]::Out.Write($stdout) }
    if ($stderr) { [Console]::Error.Write($stderr) }
    if ($process.ExitCode -ne 0) {
        exit $process.ExitCode
    }
}
finally {
    if ($passwordPointer -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    }
    if ($confirmationPointer -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($confirmationPointer)
    }
    if ($process) { $process.Dispose() }
    if ($securePassword) { $securePassword.Dispose() }
    if ($secureConfirmation) { $secureConfirmation.Dispose() }
    Remove-Variable password, confirmation, payload -ErrorAction SilentlyContinue
}
