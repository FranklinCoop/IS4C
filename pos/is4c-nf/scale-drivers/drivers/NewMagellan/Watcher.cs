
using System;
using System.IO;
using System.Diagnostics;

class Watcher
{
    private static Process current;

    public static int Main(string[] args)
    {
        var my_location = AppDomain.CurrentDomain.BaseDirectory;
        var sep = Path.DirectorySeparatorChar;
        Console.CancelKeyPress += new ConsoleCancelEventHandler(ctrlC);
        AppDomain.CurrentDomain.ProcessExit += new EventHandler(eventWrapper);
        Console.WriteLine(DateTime.Now.ToString() + ": starting driver");
        while (true) {
            var p = new Process();
            p.StartInfo.WindowStyle = ProcessWindowStyle.Minimized;
            p.StartInfo.FileName = my_location + sep + "pos.exe";
            p.Start();
            Watcher.current = p;
            p.WaitForExit();
            Console.WriteLine(DateTime.Now.ToString() + ": re-starting driver");
        }
    }

    private static void eventWrapper(object sender, EventArgs args)
    {
        Watcher.ctrlC(null, null);
    }

    private static void ctrlC(object sender, ConsoleCancelEventArgs args)
    {
        if (Watcher.current != null) {
            Watcher.current.Kill();
            Watcher.current = null;
        }
    }
}
