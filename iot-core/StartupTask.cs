using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Linq;
using System.Threading.Tasks;
using Windows.ApplicationModel.Background;
using Windows.Web.Http;
using Windows.Devices.Gpio;
using Newtonsoft.Json;
using Newtonsoft.Json.Linq;

namespace rPi_Handler
{
	public sealed class StartupTask : IBackgroundTask
	{
		private const bool IsDebugMode = true;
		private const int LongPollingTime = 5000;
		private const string PinSharp = "Pin#";
		private const string ExceptionMessageTag = "\n--Exception Message--\n";
		private const string DebugMessageTag = "--->> ";
		private JObject _postJsonData;
		private readonly Uri _requestUri = new Uri("http://10.75.15.234/rpi/raspberry.php");
		private Dictionary<string, string> _pinValues;
		private BackgroundTaskDeferral _deferral;
		//TO DO
		//delete 14,15 pins from gpio, it's looks like they are reserved pins
		private readonly int[] _gpioPinsNumbers = { 2, 3, 4, 17, 27, 22, 10, 9, 11, 5, 6, 13, 19, 26, 14, 15, 18, 23, 24, 25, 8, 7, 12, 16, 20, 21 };
		//Possible duplicate variable
		private List<int> openPins = new List<int>();
		private Dictionary<int, GpioPin> _openOutputPins;
		public async void Run(IBackgroundTaskInstance taskInstance)
		{
			Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Background Task Started!");
			//Make service running forever
			_deferral = taskInstance.GetDeferral();

			//Currently opened pins
			_openOutputPins = new Dictionary<int, GpioPin>();

			//Last pin values, initial value is unknown
			_pinValues = new Dictionary<string, string>();
			foreach (var gpioPinsNumber in _gpioPinsNumbers)
			{
				_pinValues.Add(gpioPinsNumber.ToString(), "unknown");
			}

			//initialize gpio controller (getting default gpio controller)
			InitGpioController();

			//reading pins sending their value to server and checking for commands after that
			while (true)
			{
				_postJsonData = new JObject();
				ReadPins();
				SendPinData();
				
				//Long polling every 5 seconds
				//creating a delay in loop and repeating it after 5 seconds
				await Task.Delay(LongPollingTime);
			}
		}

		//this function reads value of pin and returns value of desired pin
		private string ReadSinglePinValue(int pinNumber)
		{
			try
			{
				Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Try openning {PinSharp}{pinNumber} for reading");
				using (var gpioPin = _gpioController.OpenPin(pinNumber, GpioSharingMode.SharedReadOnly))
				{
					var pinValue = gpioPin.Read();
					Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}{PinSharp}{pinNumber} with Value: {pinValue} Opened in {gpioPin.SharingMode} Sharing Mode");
					return pinValue.ToString();
				}
			}
			catch (Exception ex)
			{
				Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Failed to read {PinSharp}" + pinNumber);
				Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}{ExceptionMessageTag}{ex.Message}{ExceptionMessageTag}");
				return "unknown";
			}

		}

		private void ReadPins()
		{
			Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Reading Pins...");
			if (_gpioController != null)
			{
				//pincount is 54, but how? really? mayble counting pins alternative states too
				Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Number of pins in device: {_gpioController.PinCount}");
				//todo fixing this
				//why from 2-27
				//why not foreach item in _gpioPinsNumbers
				for (var pinNumber = 2; pinNumber <= 27; pinNumber++)
				{
					var pinValue = ReadSinglePinValue(pinNumber);
					if (!pinValue.Equals("unknown"))
					{
						_postJsonData.Add($"{PinSharp}{pinNumber}", pinValue);
						_pinValues[pinNumber.ToString()] = pinValue;
					}
					else
					{
						//System reserved pins(14,15), possibly eeprom i2c
						//Open pins are inaccesible
						//why bother checking 14,15 pins when we know they're always inaccesible
						if (pinNumber == 14 || pinNumber == 15 || openPins.Count > 0 && openPins.Contains(pinNumber))
						{
							_postJsonData.Add($"{PinSharp}{pinNumber}", $"{_pinValues[pinNumber.ToString()]} (Last Data, Unreliable)");
						}
					}
				}
			}
			else
			{
				Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}GPIO not supported");
				//this section of code must throw exception to stop app
			}
		}

		//Getting commands from server, http get request
		private async void GetCommands()
		{
			Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Getting From Server...");
			//Create an HTTP client object
			using (var httpClient = new HttpClient())
			{
				//Send the GET request asynchronously and retrieve the response as a string.
				string httpResponseBody;

				try
				{
					//Send the GET request
					var httpResponse = await httpClient.GetAsync(_requestUri);
					httpResponse.EnsureSuccessStatusCode();
					httpResponseBody = await httpResponse.Content.ReadAsStringAsync();
					Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Getting From Server Success");

					//after reading commands then we should handle command like controlling gpio pins
					HandleCommand(httpResponseBody);
				}
				catch (Exception ex)
				{
					httpResponseBody = $"{DebugMessageTag}Error: " + ex.HResult.ToString("X") + " Message: " + ex.Message;
					Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Getting Commands From Server Failed...");
					Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}{ExceptionMessageTag}{httpResponseBody}{ExceptionMessageTag}");
				}
			}
		}

		//Posting pin values to server
		private async void SendPinData()
		{
			using (var httpClient = new HttpClient())
			{
				var httpResponseBody = "";
				var postContent = new HttpStringContent(_postJsonData.ToString(), Windows.Storage.Streams.UnicodeEncoding.Utf8, "application/json");
				//Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}JSON data to send:\n" + _postJsonData);

				try
				{
					Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Posting to Server...");
					var httpResponse = await httpClient.PostAsync(_requestUri, postContent);
					if (httpResponse.IsSuccessStatusCode)
					{
						httpResponseBody = await httpResponse.Content.ReadAsStringAsync();
						Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Posting to Server Success");
					}
					//code probably never reaches here because if request is not succesfull exception is thrown and code stops
					else
					{
						Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Posting to Server Failed");
					}
				}
				catch (Exception ex)
				{
					httpResponseBody = "Error: " + ex.HResult.ToString("X") + " Message: " + ex.Message;
					Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Posting Pin Data to Serverd Failed");
					Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}{ExceptionMessageTag}{httpResponseBody}{ExceptionMessageTag}");
				}
				finally
				{
					//Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}{httpResponseBody}");
					//after sending pin data to server now we get commands from server
					GetCommands();
				}
			}
		}

		//function for handling commands
		private void HandleCommand(string httpResponse)
		{
			Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Start Parsing Json Command...");
			var commandsJsonArray = (JArray)JsonConvert.DeserializeObject(httpResponse);
			var lastCommandJson = (JObject)commandsJsonArray.Last;
			//well, command value itself better to be a json too, more structured
			var lastCommandValue = lastCommandJson.GetValue("command").ToString();
			//var lastCommandStatus = lastCommandJson.GetValue("status").ToString();
			var commandSegments = lastCommandValue.Split(' ');

			//example
			//pin 19 high
			//pin 12 low
			//
			//this section of code is very nested
			//but we can control gpio pins from here based on commands received from server
			if (lastCommandValue.ToLower().StartsWith("pin") && commandSegments.Length == 3)
			{
				var requestedPinNumber = int.Parse(commandSegments[1]);
				var requestedPinValue = commandSegments[2];

				if (_gpioPinsNumbers.Contains(requestedPinNumber))
				{
					try
					{
						Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Openning {PinSharp}{requestedPinNumber} for output");

						if (requestedPinValue.ToLower().Equals("high"))
						{
							if (!openPins.Contains(requestedPinNumber))
							{
								var gpioPin = _gpioController.OpenPin(requestedPinNumber, GpioSharingMode.Exclusive);
								_openOutputPins.Add(requestedPinNumber, gpioPin);
								openPins.Add(requestedPinNumber);
								_pinValues[requestedPinNumber.ToString()] = "High";
								gpioPin.SetDriveMode(GpioPinDriveMode.Output);
								gpioPin.Write(GpioPinValue.High);
								Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}{PinSharp}{requestedPinNumber} set to high");
							}
							else
							{
								Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}{PinSharp}{requestedPinNumber} is already open and probably in high output");
							}
						}
						else if (requestedPinValue.ToLower().Equals("low"))
						{
							if (openPins.Contains(requestedPinNumber))
							{
								openPins.Remove(requestedPinNumber);
								foreach (var openOutputPin in _openOutputPins)
								{
									if (openOutputPin.Value.PinNumber == requestedPinNumber)
									{
										openOutputPin.Value.Dispose();
										_openOutputPins.Remove(requestedPinNumber);
									}
								}
								_pinValues[requestedPinNumber.ToString()] = "Low";
								Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}{PinSharp}{requestedPinNumber} set to low/ Pin Disposed!");
							}
							else
							{
								Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}{PinSharp}{requestedPinNumber} is not open yet so there's no need to dispose it");
							}
						}
						else
						{
							Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Probably wrong command!");
							Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Last Command: {lastCommandValue}");
						}
					}
					catch (Exception e)
					{
						Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Openning Pin Failed!");
						Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Exception Message: {e.Message}");
						Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Stack Trace: {e.StackTrace}");
					}
				}
				else
				{
					Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Wrong pin, be carefull!!!");
					Debug.WriteLineIf(IsDebugMode,
						$"{DebugMessageTag}{PinSharp}{requestedPinNumber} is not a gpio ready pin, possibly ground/power/spi/uart/spi/i2c/reserved/other");
				}
			}
			else
			{
				Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}unknown command: {lastCommandValue}");
			}
		}

		private GpioController _gpioController;
		private void InitGpioController()
		{
			Debug.WriteLineIf(IsDebugMode, $"{DebugMessageTag}Getting default GPIO Controller...");
			_gpioController = GpioController.GetDefault();
		}
	}
}
