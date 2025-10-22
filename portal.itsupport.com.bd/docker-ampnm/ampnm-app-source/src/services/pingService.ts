export interface PingResult {
  host: string;
  timestamp: string;
  success: boolean;
  output: string;
  error: string;
  statusCode: number;
}

const LOCAL_API_URL = '/api.php'; // Changed to relative path

export const performServerPing = async (host: string, count: number = 4): Promise<PingResult> => {
  try {
    const response = await fetch(`${LOCAL_API_URL}?action=manual_ping`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ host, count }),
    });

    if (!response.ok) {
      throw new Error(`Network response was not ok: ${response.statusText}`);
    }

    const phpResult = await response.json();

    if (phpResult.return_code === -1) {
      throw new Error(phpResult.output || 'Ping failed in PHP script.');
    }

    const success = phpResult.return_code === 0;

    return {
      host,
      timestamp: new Date().toISOString(),
      success: success,
      output: phpResult.output,
      error: success ? '' : phpResult.output,
      statusCode: phpResult.return_code,
    };
  } catch (error: any) {
    console.error('Local ping service error:', error);
    const errorMessage = `Failed to connect to local ping service. Please ensure your Docker container is running and port 2266 is mapped. Error: ${error.message}`;
    
    return {
      host,
      timestamp: new Date().toISOString(),
      success: false,
      output: errorMessage,
      error: errorMessage,
      statusCode: -1,
    };
  }
};

export const parsePingOutput = (output: string): { packetLoss: number; avgTime: number; minTime: number; maxTime: number } => {
  let packetLoss = 100;
  let avgTime = 0;
  let minTime = 0;
  let maxTime = 0;

  const windowsLossMatch = output.match(/Lost = \d+ \((\d+)% loss\)/);
  const windowsTimeMatch = output.match(/Minimum = (\d+)ms, Maximum = (\d+)ms, Average = (\d+)ms/);

  if (windowsLossMatch) {
    packetLoss = parseInt(windowsLossMatch[1]);
  }
  if (windowsTimeMatch) {
    minTime = parseFloat(windowsTimeMatch[1]);
    maxTime = parseFloat(windowsTimeMatch[2]);
    avgTime = parseFloat(windowsTimeMatch[3]);
  }

  const unixLossMatch = output.match(/(\d+)% packet loss/);
  const unixTimeMatch = output.match(/rtt min\/avg\/max\/mdev = ([\d.]+)\/([\d.]+)\/([\d.]+)\/([\d.]+) ms/);

  if (unixLossMatch) {
    packetLoss = parseInt(unixLossMatch[1]);
  }
  if (unixTimeMatch) {
    minTime = parseFloat(unixTimeMatch[1]);
    avgTime = parseFloat(unixTimeMatch[2]);
    maxTime = parseFloat(unixTimeMatch[3]);
  }

  return {
    packetLoss,
    minTime,
    avgTime,
    maxTime,
  };
};