package pl.plock.pgm.ui

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp

/** Główny układ: zakładki Gry / Ranking / Profil oraz ekrany gry i rozgrywki. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PgmApp(vm: AppViewModel) {
    val snackbar = remember { SnackbarHostState() }

    LaunchedEffect(vm.message) {
        vm.message?.let {
            snackbar.showSnackbar(it)
            vm.message = null
        }
    }

    BackHandler(enabled = vm.route != Route.Tabs) { vm.back() }

    // Rozgrywka ma własny, pełnoekranowy układ z mapą
    val route = vm.route
    if (route is Route.Play) {
        PlayScreen(vm, snackbar)
        return
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Text(
                        when {
                            route is Route.Game -> vm.game?.title ?: "Gra"
                            vm.tab == Tab.EXPLORE -> "Odkrywaj Płock"
                            vm.tab == Tab.SHOP -> "Sklep punktów"
                            vm.tab == Tab.RANKING -> "Ranking graczy"
                            vm.tab == Tab.PROFILE -> "Twój profil"
                            else -> "Płockie Gry Miejskie"
                        },
                        fontWeight = FontWeight.Bold,
                        maxLines = 1,
                    )
                },
                navigationIcon = {
                    if (route is Route.Game) {
                        androidx.compose.material3.IconButton(onClick = { vm.back() }) {
                            PgmIcon(PgmIcons.BACK, "Wróć", tint = MaterialTheme.colorScheme.onPrimary)
                        }
                    }
                },
                actions = {
                    if (route == Route.Tabs && vm.tab == Tab.GAMES) {
                        androidx.compose.material3.IconButton(onClick = { vm.loadGames() }) {
                            PgmIcon(PgmIcons.REFRESH, "Odśwież listę gier", tint = MaterialTheme.colorScheme.onPrimary)
                        }
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.primary,
                    titleContentColor = MaterialTheme.colorScheme.onPrimary,
                ),
            )
        },
        bottomBar = {
            if (route == Route.Tabs) {
                NavigationBar {
                    TabItem(vm, Tab.GAMES, PgmIcons.MAP, "Gry")
                    TabItem(vm, Tab.EXPLORE, PgmIcons.EXPLORE, "Odkrywaj")
                    TabItem(vm, Tab.SHOP, PgmIcons.STORE, "Sklep")
                    TabItem(vm, Tab.RANKING, PgmIcons.TROPHY, "Ranking")
                    TabItem(vm, Tab.PROFILE, PgmIcons.PERSON, "Profil")
                }
            }
        },
        snackbarHost = { SnackbarHost(snackbar) },
    ) { padding ->
        val modifier = Modifier.padding(padding)
        when {
            vm.player == null && vm.connectionProblem -> ConnectionHelpScreen(vm, modifier)
            vm.player == null && vm.startupError != null -> ErrorBox(vm.startupError!!, onRetry = { vm.startup() }, modifier)
            vm.player == null -> LoadingBox(modifier)
            route is Route.Game -> GameDetailScreen(vm, route.gameId, modifier)
            vm.tab == Tab.EXPLORE -> ExploreScreen(vm, modifier)
            vm.tab == Tab.SHOP -> ShopScreen(vm, modifier)
            vm.tab == Tab.RANKING -> RankingScreen(vm, modifier)
            vm.tab == Tab.PROFILE -> ProfileScreen(vm, modifier)
            else -> GamesScreen(vm, modifier)
        }
    }

    vm.confirmStartGameId?.let { gameId ->
        vm.game?.takeIf { it.id == gameId }?.let { StartGameDialog(vm, it) }
    }

    if (vm.showOnboarding && vm.player != null) {
        OnboardingDialog(vm.player?.nickname.orEmpty()) { vm.dismissOnboarding() }
    }
}

/** Ekran powitalny – zasady gry w czterech krokach (pokazywany raz). */
@Composable
private fun OnboardingDialog(nickname: String, onDone: () -> Unit) {
    androidx.compose.material3.AlertDialog(
        onDismissRequest = onDone,
        title = { Text("Witaj w Płockich Grach Miejskich!", fontWeight = FontWeight.Bold) },
        text = {
            androidx.compose.foundation.layout.Column(
                verticalArrangement = androidx.compose.foundation.layout.Arrangement.spacedBy(10.dp),
            ) {
                Text("Grasz jako $nickname – nick zmienisz w Profilu.")
                Text("1. Wybierz grę i kliknij „Rozpocznij grę”.")
                Text("2. Idź do punktu na mapie – gdy będziesz blisko, zadanie się odblokuje.")
                Text("3. Wykonaj zadanie: odpowiedz na pytanie, zrób zdjęcie albo nagraj film.")
                Text("4. Zbieraj punkty i walcz o miejsce w rankingu.")
                Text(
                    "Nie jesteś w Płocku? Włącz w Profilu „Tryb demo” i ustawiaj pozycję palcem na mapie.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        },
        confirmButton = { androidx.compose.material3.Button(onClick = onDone) { Text("Zaczynamy!") } },
    )
}

@Composable
private fun androidx.compose.foundation.layout.RowScope.TabItem(vm: AppViewModel, tab: Tab, icon: String, label: String) {
    NavigationBarItem(
        selected = vm.tab == tab,
        onClick = { vm.selectTab(tab) },
        icon = { PgmIcon(icon, null) },
        label = { Text(label) },
    )
}

/** Podsumowanie przed startem gry: czas, punkty trasy, GPS, zadania z aparatem i limit dnia. */
@Composable
private fun StartGameDialog(vm: AppViewModel, game: pl.plock.pgm.data.GameDetail) {
    val limits = vm.limits
    val noGamesLeft = limits != null && limits.remaining <= 0
    val media = game.waypoints.mapNotNull { it.task?.taskType }.filter { it == "photo" || it == "video" }
    androidx.compose.material3.AlertDialog(
        onDismissRequest = { vm.confirmStartGameId = null },
        title = { Text(game.title, fontWeight = FontWeight.Bold) },
        text = {
            androidx.compose.foundation.layout.Column(
                verticalArrangement = androidx.compose.foundation.layout.Arrangement.spacedBy(8.dp),
            ) {
                durationLabel(game.estimatedDurationMinutes)?.let { Text("Szacowany czas: $it") }
                Text("Punkty trasy: ${game.waypointCount} – odwiedzasz je po kolei")
                Text("Do zdobycia: do ${game.maxScore} pkt (w tym ${game.completionPoints} za ukończenie)")
                Text("Potrzebna jest lokalizacja GPS – zgodę dasz za chwilę.")
                if (media.isNotEmpty()) {
                    Text(
                        "W grze są zadania z aparatem (" +
                            listOfNotNull("zdjęcie".takeIf { "photo" in media }, "film".takeIf { "video" in media }).joinToString(", ") + ")."
                    )
                }
                Text(
                    when {
                        limits == null -> "Sprawdzam dzienny limit gier…"
                        noGamesLeft -> "Wykorzystałeś dzisiejszy limit gier (${limits.dailyLimit}). Limit odnowi się jutro."
                        limits.remaining == 1 -> "Pozostała Ci 1 gra na dziś. Rozpoczęta gra liczy się do limitu, nawet jeśli jej nie skończysz."
                        else -> "Możesz rozpocząć jeszcze ${limits.remaining} ${plural(limits.remaining, "grę", "gry", "gier")} dzisiaj. " +
                            "Rozpoczęta gra liczy się do limitu."
                    },
                    fontWeight = FontWeight.SemiBold,
                    color = if (noGamesLeft) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.primary,
                )
            }
        },
        confirmButton = {
            androidx.compose.material3.Button(onClick = { vm.play(game.id) }, enabled = !noGamesLeft && !vm.busy) {
                Text("Rozpocznij")
            }
        },
        dismissButton = { androidx.compose.material3.TextButton(onClick = { vm.confirmStartGameId = null }) { Text("Anuluj") } },
    )
}
