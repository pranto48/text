import { useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { RefreshCw, Docker, AlertTriangle } from "lucide-react";
import { showSuccess, showError, showLoading, dismissToast } from "@/utils/toast";
import { updateDockerImage } from "@/services/networkDeviceService";

const DockerUpdate = () => {
  const [isUpdating, setIsUpdating] = useState(false);
  const dockerImageName = "arifmahmudpranto/ampnm";

  const handleUpdate = async () => {
    if (!window.confirm(`Are you sure you want to pull and restart the Docker image: ${dockerImageName}? This will briefly interrupt the application.`)) {
      return;
    }

    setIsUpdating(true);
    const toastId = showLoading("Starting Docker image pull...");

    try {
      const result = await updateDockerImage(dockerImageName);
      
      dismissToast(toastId);
      if (result.success) {
        // The PHP backend returns a message containing the Dyad command tag.
        showSuccess(result.message);
      } else {
        throw new Error(result.error || "Update failed with unknown error.");
      }
    } catch (error: any) {
      dismissToast(toastId);
      showError(error.message || "An unexpected error occurred during the Docker update process.");
    } finally {
      setIsUpdating(false);
    }
  };

  return (
    <Card className="bg-card text-foreground border-border">
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-primary">
          <Docker className="h-5 w-5" />
          Application Update (Docker)
        </CardTitle>
        <CardDescription>
          Pull the latest Docker image and prepare the application container for restart. This action is restricted to administrators.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="p-4 border rounded-lg bg-yellow-500/10 text-yellow-400 flex items-start gap-3 border-yellow-500">
          <AlertTriangle className="h-5 w-5 flex-shrink-0 mt-0.5" />
          <div>
            <p className="font-medium">Warning:</p>
            <p className="text-sm">
              This action executes shell commands (`docker pull`) inside the container. You must manually click the **Restart** button above after the pull succeeds to apply the new image.
            </p>
          </div>
        </div>
        
        <div className="flex items-center justify-between p-3 border rounded-lg bg-muted border-border">
          <span className="font-medium">Target Image:</span>
          <code className="font-mono text-sm text-foreground">{dockerImageName}</code>
        </div>

        <Button 
          onClick={handleUpdate} 
          disabled={isUpdating} 
          className="w-full bg-primary hover:bg-primary/90 text-primary-foreground"
        >
          <RefreshCw className={`h-4 w-4 mr-2 ${isUpdating ? 'animate-spin' : ''}`} />
          {isUpdating ? "Pulling Image..." : "Pull Latest Image"}
        </Button>
      </CardContent>
    </Card>
  );
};

export default DockerUpdate;