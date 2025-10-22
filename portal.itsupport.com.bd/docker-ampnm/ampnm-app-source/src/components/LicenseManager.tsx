import { useState, useEffect, useCallback } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Key, RefreshCw, Info, AlertCircle } from "lucide-react";
import { showSuccess, showError, showLoading, dismissToast } from "@/utils/toast";
import {
  LicenseStatus,
  forceLicenseRecheck,
  updateAppLicenseKey,
} from "@/services/networkDeviceService";

interface LicenseManagerProps {
  licenseStatus: LicenseStatus;
  fetchLicenseStatus: () => Promise<void>;
}

const LicenseManager = ({ licenseStatus, fetchLicenseStatus }: LicenseManagerProps) => {
  const [newLicenseKey, setNewLicenseKey] = useState("");
  const [isUpdatingKey, setIsUpdatingKey] = useState(false);
  const [isRechecking, setIsRechecking] = useState(false);

  useEffect(() => {
    if (licenseStatus.app_license_key) {
      setNewLicenseKey(licenseStatus.app_license_key);
    }
  }, [licenseStatus.app_license_key]);

  const handleUpdateLicenseKey = async () => {
    if (!newLicenseKey.trim()) {
      showError("Please enter a new license key.");
      return;
    }

    setIsUpdatingKey(true);
    const toastId = showLoading("Updating license key...");
    try {
      const result = await updateAppLicenseKey(newLicenseKey.trim());
      if (result.success) {
        dismissToast(toastId);
        showSuccess(result.message);
        await fetchLicenseStatus(); // Re-fetch to update UI with new status
      } else {
        throw new Error(result.message || "Failed to update license key.");
      }
    } catch (error: any) {
      dismissToast(toastId);
      showError(error.message || "An error occurred while updating the license key.");
    } finally {
      setIsUpdatingKey(false);
    }
  };

  const handleRecheckLicense = async () => {
    setIsRechecking(true);
    const toastId = showLoading("Rechecking license status...");
    try {
      const result = await forceLicenseRecheck();
      if (result.success) {
        dismissToast(toastId);
        showSuccess(result.message);
        await fetchLicenseStatus(); // Re-fetch to update UI with latest status
      } else {
        throw new Error(result.message || "Failed to recheck license.");
      }
    } catch (error: any) {
      dismissToast(toastId);
      showError(error.message || "An error occurred while rechecking the license.");
    } finally {
      setIsRechecking(false);
    }
  };

  const getLicenseBadgeVariant = (statusCode: LicenseStatus['license_status_code']) => {
    switch (statusCode) {
      case 'active': return 'default';
      case 'grace_period': return 'secondary';
      case 'expired':
      case 'disabled':
      case 'error':
      case 'in_use':
      default: return 'destructive';
    }
  };

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Key className="h-5 w-5" />
            License Management
          </CardTitle>
          <CardDescription>
            View your current application license status and update your license key.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <div className="flex items-center justify-between p-3 border rounded-lg bg-muted">
              <div className="flex items-center gap-2">
                <Info className="h-4 w-4 text-blue-500" />
                <span className="font-medium">Current License Status:</span>
              </div>
              <Badge variant={getLicenseBadgeVariant(licenseStatus.license_status_code)}>
                {licenseStatus.license_status_code.replace(/_/g, ' ')}
              </Badge>
            </div>

            <div className="p-3 border rounded-lg bg-muted">
              <p className="text-sm text-muted-foreground mb-2">
                {licenseStatus.license_message}
              </p>
              {licenseStatus.license_grace_period_end && (
                <p className="text-sm text-orange-500">
                  Grace period ends: {new Date(licenseStatus.license_grace_period_end * 1000).toLocaleString()}
                </p>
              )}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Card>
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium">Max Devices</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold">{licenseStatus.max_devices}</div>
                  <p className="text-xs text-muted-foreground">Allowed by your license</p>
                </CardContent>
              </Card>
              <Card>
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm font-medium">Installation ID</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-sm font-mono break-all">{licenseStatus.installation_id || 'N/A'}</div>
                  <p className="text-xs text-muted-foreground">Unique ID for this AMPNM instance</p>
                </CardContent>
              </Card>
            </div>

            <div className="space-y-2">
              <h3 className="text-sm font-medium">Your Application License Key</h3>
              <Input
                type="text"
                placeholder="Enter your new license key here"
                value={newLicenseKey}
                onChange={(e) => setNewLicenseKey(e.target.value)}
                className="font-mono"
              />
              <div className="flex gap-2">
                <Button
                  onClick={handleUpdateLicenseKey}
                  disabled={isUpdatingKey}
                  className="flex-1"
                >
                  <Key className={`h-4 w-4 mr-2 ${isUpdatingKey ? 'animate-spin' : ''}`} />
                  {isUpdatingKey ? "Updating..." : "Update License Key"}
                </Button>
                <Button
                  onClick={handleRecheckLicense}
                  disabled={isRechecking}
                  variant="outline"
                  className="flex-1"
                >
                  <RefreshCw className={`h-4 w-4 mr-2 ${isRechecking ? 'animate-spin' : ''}`} />
                  {isRechecking ? "Rechecking..." : "Recheck License"}
                </Button>
              </div>
            </div>

            {(licenseStatus.license_status_code === 'expired' || licenseStatus.license_status_code === 'grace_period' || licenseStatus.license_status_code === 'disabled' || licenseStatus.license_status_code === 'in_use') && (
              <div className="p-3 border rounded-lg bg-red-500/10 text-red-400 flex items-start gap-2">
                <AlertCircle className="h-5 w-5 flex-shrink-0 mt-0.5" />
                <div>
                  <p className="font-medium">Action Required:</p>
                  <p className="text-sm">
                    Your license is not active. Please update your license key above or visit the
                    <a href="https://portal.itsupport.com.bd/products.php" target="_blank" rel="noopener noreferrer" className="underline ml-1">IT Support BD Portal</a>
                    to purchase or renew your license.
                  </p>
                </div>
              </div>
            )}
            <div className="p-3 border rounded-lg bg-blue-500/10 text-blue-400 flex items-start gap-2">
              <Info className="h-5 w-5 flex-shrink-0 mt-0.5" />
              <div>
                <p className="font-medium">Need Support?</p>
                <p className="text-sm">
                  If you encounter any issues with your license or need assistance, please visit our
                  <a href="https://portal.itsupport.com.bd/support.php" target="_blank" rel="noopener noreferrer" className="underline ml-1">Support Ticket System</a>.
                </p>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
};

export default LicenseManager;