package pl.plock.pgm.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Card
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import pl.plock.pgm.Config

/** Ranking ogólny – suma najlepszych wyników ze wszystkich gier. */
@Composable
fun RankingScreen(vm: AppViewModel, modifier: Modifier = Modifier) {
    LaunchedEffect(Unit) { vm.loadOverallRanking() }
    Column(
        modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text(
            "Suma najlepszych wyników ze wszystkich ukończonych gier.",
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        RankingList(vm.overallRanking, vm.player?.id, empty = "Ranking jest pusty – ukończ pierwszą grę!")
        TextButton(onClick = { vm.loadOverallRanking() }) { Text("Odśwież") }
    }
}

/** Profil gracza: nick, historia gier, tryb demo. */
@Composable
fun ProfileScreen(vm: AppViewModel, modifier: Modifier = Modifier) {
    LaunchedEffect(Unit) { vm.loadProfile() }
    var editing by remember { mutableStateOf(false) }

    Column(
        modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        Card(Modifier.fillMaxWidth()) {
            Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text("Grasz jako", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text(vm.player?.nickname ?: "…", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
                }
                TextButton(onClick = { editing = true }) { Text("Zmień nick") }
            }
        }

        Row(horizontalArrangement = Arrangement.spacedBy(12.dp), modifier = Modifier.fillMaxWidth()) {
            Card(Modifier.weight(1f).clickable { vm.selectTab(Tab.SHOP) }) {
                Column(Modifier.padding(14.dp)) {
                    Text("Punkty do wydania", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text(vm.balance?.balance?.toString() ?: "…", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
                }
            }
            Card(Modifier.weight(1f)) {
                Column(Modifier.padding(14.dp)) {
                    Text("Gry na dziś", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text(
                        vm.limits?.let { "${it.remaining} z ${it.dailyLimit}" } ?: "…",
                        style = MaterialTheme.typography.headlineSmall,
                        fontWeight = FontWeight.Bold,
                    )
                }
            }
        }

        Text("Twoje gry", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
        val history = vm.profile?.history.orEmpty()
        if (history.isEmpty()) {
            Text("Nie rozpocząłeś jeszcze żadnej gry.", color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        for (entry in history) {
            Row(
                Modifier.fillMaxWidth().clickable(enabled = entry.gameAvailable) { vm.openGame(entry.gameId) }.padding(vertical = 6.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Column(Modifier.weight(1f)) {
                    Text(entry.gameTitle, fontWeight = FontWeight.SemiBold)
                    Text(
                        (if (entry.status == "completed") "Ukończona ${dateLabel(entry.finishedAt)}"
                        else "W trakcie – rozpoczęta ${dateLabel(entry.startedAt)}") +
                            " · punkty trasy ${entry.completedWaypoints}/${entry.totalWaypoints}",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    if (!entry.gameAvailable) {
                        Text("Gra nie jest już dostępna w aplikacji", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.tertiary)
                    }
                }
                Text("${entry.score} pkt", fontWeight = FontWeight.Bold)
            }
            HorizontalDivider()
        }

        Text("Ustawienia", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text("Tryb demo")
                Text(
                    "Pozycję ustawiasz przytrzymaniem palca na mapie – do pokazu gry bez chodzenia po mieście.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Switch(checked = vm.demoMode, onCheckedChange = { vm.toggleDemoMode(it) })
        }
        Text(
            "Serwer: ${vm.serverUrl}",
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
    }

    if (editing) {
        var nickname by remember { mutableStateOf(vm.player?.nickname.orEmpty()) }
        val allowed = vm.player?.nicknameChangeAllowed != false
        AlertDialog(
            onDismissRequest = { editing = false },
            title = { Text("Zmień nick") },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    if (allowed) {
                        Text("Nick możesz zmienić raz w roku – wybierz dobrze!", style = MaterialTheme.typography.bodySmall)
                        OutlinedTextField(
                            value = nickname,
                            onValueChange = { if (it.length <= 40) nickname = it },
                            singleLine = true,
                            supportingText = { Text("3–40 znaków: litery, cyfry, spacje, . - _") },
                        )
                    } else {
                        Text(
                            "Nick można zmienić raz w roku. Kolejna zmiana będzie możliwa " +
                                (vm.player?.nicknameNextChangeAt?.let { "od ${dateLabel(it).substringBefore(',')}" } ?: "później") + ".",
                        )
                    }
                }
            },
            confirmButton = {
                TextButton(
                    onClick = {
                        vm.rename(nickname)
                        editing = false
                    },
                    enabled = allowed && nickname.trim().length >= 3 && nickname.trim() != vm.player?.nickname,
                ) { Text("Zapisz") }
            },
            dismissButton = { TextButton(onClick = { editing = false }) { Text("Anuluj") } },
        )
    }
}
