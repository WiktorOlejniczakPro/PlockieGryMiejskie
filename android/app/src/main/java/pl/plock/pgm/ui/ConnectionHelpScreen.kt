package pl.plock.pgm.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import pl.plock.pgm.Config

/**
 * Brak połączenia z serwerem przy starcie: co sprawdzić i pole na adres serwera
 * (np. IP komputera w tej samej sieci Wi-Fi) – bez zmieniania kodu aplikacji.
 */
@Composable
fun ConnectionHelpScreen(vm: AppViewModel, modifier: Modifier = Modifier) {
    var address by remember { mutableStateOf(vm.serverUrl) }
    val emulator = Config.isEmulator()

    Column(
        modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text("Brak połączenia z serwerem", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
        Text(
            "Aplikacja nie dostała odpowiedzi od serwera PGM (XAMPP na komputerze).",
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )

        Card(Modifier.fillMaxWidth()) {
            Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text("Sprawdź po kolei", fontWeight = FontWeight.Bold)
                Text("1. W panelu XAMPP Apache i MySQL są zielone.")
                Text("2. Na komputerze w przeglądarce działa: localhost/pgm/public/api/v1/games")
                if (emulator) {
                    Text("3. Emulator łączy się z komputerem sam (adres 10.0.2.2) – wystarczy punkt 1 i 2.")
                } else {
                    Text("3. Telefon na kablu USB: w cmd na komputerze (folder platform-tools) wpisz:")
                    Text("adb reverse tcp:8080 tcp:80", fontFamily = FontFamily.Monospace, fontWeight = FontWeight.Bold)
                    Text(
                        "Trzeba to powtórzyć po każdym odłączeniu kabla, restarcie telefonu albo Android Studio.",
                        style = MaterialTheme.typography.bodySmall,
                    )
                    Text("4. Test na telefonie w Chrome: 127.0.0.1:8080/pgm/public/api/v1/games – ma pokazać tekst JSON.")
                    Text(
                        "Telefon przez Wi-Fi (bez kabla): wpisz niżej adres IP komputera z polecenia ipconfig, np. 192.168.1.15.",
                        style = MaterialTheme.typography.bodySmall,
                    )
                }
            }
        }

        OutlinedTextField(
            value = address,
            onValueChange = { address = it },
            label = { Text("Adres serwera") },
            singleLine = true,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri),
            modifier = Modifier.fillMaxWidth(),
        )
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            Button(onClick = { vm.connectTo(address) }, enabled = !vm.connecting) { Text("Połącz z tym adresem") }
            OutlinedButton(onClick = { vm.startup() }, enabled = !vm.connecting) {
                if (vm.connecting) CircularProgressIndicator(Modifier.size(16.dp), strokeWidth = 2.dp) else Text("Szukaj ponownie")
            }
        }

        if (vm.triedUrls.isNotEmpty()) {
            Text("Sprawdzone adresy:", style = MaterialTheme.typography.labelMedium)
            for (url in vm.triedUrls) {
                Text("✗ $url", style = MaterialTheme.typography.bodySmall, fontFamily = FontFamily.Monospace)
            }
        }
    }
}
