package pl.plock.pgm.ui

import android.app.Application
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.launch
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.asRequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.withContext
import pl.plock.pgm.data.probeServer
import pl.plock.pgm.data.probeServerError
import pl.plock.pgm.Config
import pl.plock.pgm.data.ApiClient
import pl.plock.pgm.data.NavRoute
import pl.plock.pgm.data.RouteMode
import pl.plock.pgm.data.RoutingClient
import pl.plock.pgm.data.distanceFrom
import pl.plock.pgm.data.straightRoute
import pl.plock.pgm.data.Balance
import pl.plock.pgm.data.DailyLimit
import pl.plock.pgm.data.Redemption
import pl.plock.pgm.data.Reward
import pl.plock.pgm.data.Category
import pl.plock.pgm.data.GameDetail
import pl.plock.pgm.data.GameSummary
import pl.plock.pgm.data.Place
import pl.plock.pgm.data.Player
import pl.plock.pgm.data.PlayerProfile
import pl.plock.pgm.data.PlayerStore
import pl.plock.pgm.data.RankingEntry
import pl.plock.pgm.data.SessionState
import pl.plock.pgm.data.toApiError
import pl.plock.pgm.location.LocationTracker
import pl.plock.pgm.location.PlayerLocation
import java.io.File
import java.util.UUID

/** Ekrany aplikacji. Zakładki to ekrany główne, reszta otwiera się „nad nimi”. */
sealed interface Route {
    data object Tabs : Route
    data class Game(val gameId: Int) : Route
    data class Play(val gameId: Int) : Route
}

enum class Tab { GAMES, EXPLORE, SHOP, RANKING, PROFILE }

/** Odpowiedź na zadanie przygotowana w oknie zadania. */
sealed interface Answer {
    data class Options(val optionIds: List<Int>) : Answer
    data class Text(val text: String) : Answer
    data class Media(val file: File, val mimeType: String, val comment: String) : Answer
    data object Info : Answer
}

class AppViewModel(application: Application) : AndroidViewModel(application) {

    private val store = PlayerStore(application)
    /** Adres API, z którym rozmawia aplikacja (zapamiętany po udanym połączeniu). */
    var serverUrl by mutableStateOf(store.serverUrl ?: Config.API_BASE_URL)
        private set
    private var api = ApiClient.create(serverUrl) { store.token }
    /** Brak połączenia przy starcie – ekran pokazuje pomoc i pole na adres serwera. */
    var connectionProblem by mutableStateOf(false)
        private set
    /** Sprawdzone adresy serwera i przyczyna błędu przy każdym (ekran pomocy). */
    var triedUrls by mutableStateOf<List<Pair<String, String>>>(emptyList())
        private set
    var connecting by mutableStateOf(false)
        private set

    private fun useServer(url: String) {
        serverUrl = url
        store.serverUrl = url
        api = ApiClient.create(url) { store.token }
    }

    /** Adres wpisany ręcznie na ekranie błędu połączenia. */
    fun connectTo(input: String) {
        val url = Config.normalizeServerUrl(input)
        if (url == null) {
            message = "Wpisz adres serwera, np. 192.168.1.15"
            return
        }
        useServer(url)
        startup(probe = false)
    }
    val locationTracker = LocationTracker(application)

    // --- nawigacja -------------------------------------------------------
    var route by mutableStateOf<Route>(Route.Tabs)
        private set
    var tab by mutableStateOf(Tab.GAMES)

    // --- gracz -----------------------------------------------------------
    var player by mutableStateOf<Player?>(null)
        private set
    var startupError by mutableStateOf<String?>(null)
        private set
    var profile by mutableStateOf<PlayerProfile?>(null)
        private set

    // --- lista gier --------------------------------------------------------
    var categories by mutableStateOf<List<Category>>(emptyList())
        private set
    var selectedCategory by mutableStateOf<String?>(null)
        private set
    var search by mutableStateOf("")
    var games by mutableStateOf<List<GameSummary>>(emptyList())
        private set
    var gamesLoading by mutableStateOf(false)
        private set
    var gamesError by mutableStateOf<String?>(null)
        private set

    // --- szczegóły gry i ranking ---------------------------------------------
    var game by mutableStateOf<GameDetail?>(null)
        private set
    var gameRanking by mutableStateOf<List<RankingEntry>>(emptyList())
        private set
    var overallRanking by mutableStateOf<List<RankingEntry>>(emptyList())
        private set
    var loading by mutableStateOf(false)
        private set

    // --- rozgrywka -----------------------------------------------------------
    var session by mutableStateOf<SessionState?>(null)
        private set
    var busy by mutableStateOf(false)
        private set
    /** Trwa zgłaszanie dotarcia do punktu (osobno od wysyłania odpowiedzi). */
    var arriving by mutableStateOf(false)
        private set
    /** Licznik dotarć – ekran gry reaguje na jego zmianę (wibracja). */
    var arrivals by mutableStateOf(0)
        private set
    var selectedWaypointId by mutableStateOf<Int?>(null)
    /** Tryb demo działa tylko na emulatorze – na telefonie zawsze liczy się prawdziwy GPS. */
    val demoAvailable = Config.isEmulator()
    var demoMode by mutableStateOf(store.demoMode && demoAvailable)
        private set
    var simulatedLocation by mutableStateOf<PlayerLocation?>(null)

    // --- Odkrywaj Płock (miejsca z danych miasta) ----------------------------
    var exploreGroup by mutableStateOf(PLACE_GROUPS.first())
        private set
    var exploreRadiusM by mutableStateOf(1000)
        private set
    /** Punkt, wokół którego szukamy (null = moja pozycja albo centrum Płocka). */
    var exploreCenter by mutableStateOf<Pair<Double, Double>?>(null)
        private set
    var places by mutableStateOf<List<Place>>(emptyList())
        private set
    var placesLoading by mutableStateOf(false)
        private set
    var selectedPlace by mutableStateOf<Place?>(null)

    // --- sklep punktów ------------------------------------------------------------
    var balance by mutableStateOf<Balance?>(null)
        private set
    var rewards by mutableStateOf<List<Reward>>(emptyList())
        private set
    var redemptions by mutableStateOf<List<Redemption>>(emptyList())
        private set
    var shopLoading by mutableStateOf(false)
        private set
    var shopError by mutableStateOf<String?>(null)
        private set
    /** Właśnie wydana nagroda – okno z kodem odbioru. */
    var lastRedemption by mutableStateOf<Redemption?>(null)

    // --- limit gier na dziś i potwierdzenie startu ----------------------------
    var limits by mutableStateOf<DailyLimit?>(null)
        private set
    /** Gra, dla której pokazujemy podsumowanie przed startem. */
    var confirmStartGameId by mutableStateOf<Int?>(null)

    /** Ekran powitalny przy pierwszym uruchomieniu. */
    var showOnboarding by mutableStateOf(!store.onboardingSeen)
        private set

    /** Jednorazowy komunikat (pasek na dole ekranu). */
    var message by mutableStateOf<String?>(null)

    init {
        startup()
    }

    /** Pierwsze uruchomienie: zakłada konto gracza (bez logowania), potem ładuje gry. */
    fun startup(probe: Boolean = true) {
        startupError = null
        connectionProblem = false
        connecting = true
        viewModelScope.launch {
            try {
                // nie ma odpowiedzi pod zapamiętanym adresem – szukamy działającego
                if (probe && !withContext(Dispatchers.IO) { probeServer(serverUrl) }) {
                    val candidates = (listOf(serverUrl) + Config.candidateUrls()).distinct()
                    val results = mutableListOf<Pair<String, String>>()
                    val found = withContext(Dispatchers.IO) {
                        candidates.firstOrNull { url ->
                            val error = probeServerError(url)
                            if (error != null) results += url to error
                            error == null
                        }
                    }
                    if (found == null) {
                        triedUrls = results
                        connectionProblem = true
                        startupError = "Nie mogę połączyć się z serwerem PGM."
                        return@launch
                    }
                    if (found != serverUrl) useServer(found)
                }
                if (store.token == null) {
                    val result = api.register(emptyMap()).data
                    store.token = result.token
                    player = result.player
                } else {
                    player = api.me().data.player
                }
            } catch (e: Exception) {
                val error = e.toApiError()
                if (error.code == 401) {
                    // token nieważny (np. baza została zaimportowana od nowa) – nowe konto
                    store.token = null
                    startup(probe = false)
                    return@launch
                }
                if (error.code == 0) {
                    triedUrls = listOf(serverUrl to error.message.orEmpty())
                    connectionProblem = true
                }
                startupError = error.message
                return@launch
            } finally {
                connecting = false
            }
            store.serverUrl = serverUrl
            loadCategories()
            loadGames()
            loadProfile()
        }
    }

    // ------------------------------------------------------------------------
    //  Nawigacja
    // ------------------------------------------------------------------------

    fun openGame(gameId: Int) {
        route = Route.Game(gameId)
        loadGame(gameId)
        loadProfile() // czy gra jest „w trakcie” (przycisk Kontynuuj)
    }

    fun back() {
        stopRoute()
        routeDismissed = false
        route = when (val current = route) {
            is Route.Play -> {
                locationTracker.stop()
                loadGame(current.gameId)
                Route.Game(current.gameId)
            }
            else -> Route.Tabs
        }
        loadProfile()
    }

    fun selectTab(newTab: Tab) {
        if (newTab != tab) stopRoute()
        tab = newTab
        when (newTab) {
            Tab.GAMES -> if (games.isEmpty()) loadGames()
            Tab.EXPLORE -> if (places.isEmpty()) loadPlaces(null)
            Tab.SHOP -> loadShop()
            Tab.RANKING -> loadOverallRanking()
            Tab.PROFILE -> loadProfile()
        }
    }

    // ------------------------------------------------------------------------
    //  Lista gier
    // ------------------------------------------------------------------------

    fun selectCategory(slug: String?) {
        selectedCategory = slug
        loadGames()
    }

    fun loadGames() {
        gamesLoading = true
        gamesError = null
        viewModelScope.launch {
            try {
                games = api.games(selectedCategory, search.trim().ifEmpty { null }).data
            } catch (e: Exception) {
                gamesError = e.toApiError().message
            } finally {
                gamesLoading = false
            }
        }
    }

    private suspend fun loadCategories() {
        try {
            categories = api.categories().data
        } catch (e: Exception) {
            // bez kategorii lista gier nadal działa
        }
    }

    // ------------------------------------------------------------------------
    //  Szczegóły gry, rankingi, profil
    // ------------------------------------------------------------------------

    private fun loadGame(gameId: Int) {
        if (game?.id != gameId) {
            game = null
            gameRanking = emptyList()
        }
        loading = true
        viewModelScope.launch {
            try {
                game = api.game(gameId).data
                gameRanking = api.gameRanking(gameId).data
            } catch (e: Exception) {
                message = e.toApiError().message
            } finally {
                loading = false
            }
        }
    }

    fun loadOverallRanking() {
        viewModelScope.launch {
            try {
                overallRanking = api.overallRanking().data
            } catch (e: Exception) {
                message = e.toApiError().message
            }
        }
    }

    fun loadProfile() {
        viewModelScope.launch {
            try {
                profile = api.me().data
                player = profile?.player
                profile?.balance?.let { balance = it }
                profile?.limits?.let { limits = it }
            } catch (e: Exception) {
                message = e.toApiError().message
            }
        }
    }

    fun rename(nickname: String) {
        viewModelScope.launch {
            try {
                player = api.rename(mapOf("nickname" to nickname.trim())).data.player
                loadProfile()
                message = "Zmieniono nick."
            } catch (e: Exception) {
                message = e.toApiError().message
            }
        }
    }

    fun dismissOnboarding() {
        showOnboarding = false
        store.onboardingSeen = true
    }

    // ------------------------------------------------------------------------
    //  Odkrywaj Płock
    // ------------------------------------------------------------------------

    fun selectPlaceGroup(group: PlaceGroup, myLocation: PlayerLocation?) {
        exploreGroup = group
        loadPlaces(myLocation)
    }

    fun selectExploreRadius(radius: Int, myLocation: PlayerLocation?) {
        exploreRadiusM = radius
        loadPlaces(myLocation)
    }

    /** Szukaj wokół wskazanego na mapie punktu (przytrzymanie palca). */
    fun exploreAt(lat: Double, lng: Double) {
        exploreCenter = lat to lng
        loadPlaces(null)
    }

    /** Wróć do szukania wokół mojej pozycji. */
    fun exploreAroundMe(myLocation: PlayerLocation?) {
        exploreCenter = null
        loadPlaces(myLocation)
    }

    fun loadPlaces(myLocation: PlayerLocation?) {
        val center = exploreCenter ?: myLocation?.let { it.latitude to it.longitude }
        placesLoading = true
        viewModelScope.launch {
            try {
                places = api.places(
                    center?.first, center?.second, exploreRadiusM,
                    exploreGroup.slugs.joinToString(",").ifEmpty { null },
                ).data
                if (selectedPlace != null && places.none { it.id == selectedPlace?.id }) selectedPlace = null
            } catch (e: Exception) {
                message = e.toApiError().message
            } finally {
                placesLoading = false
            }
        }
    }

    // ------------------------------------------------------------------------
    //  Sklep punktów
    // ------------------------------------------------------------------------

    /**
     * Nagrody z Kreatora nagród (panel) i wymiany gracza.
     * @param silent odświeżenie w tle – bez paska postępu i bez zmiany błędu, gdy chwilowo brak sieci
     */
    fun loadShop(silent: Boolean = false) {
        if (shopLoading) return
        if (!silent) {
            shopLoading = true
            shopError = null
        }
        viewModelScope.launch {
            try {
                val data = api.rewards().data
                balance = data.balance
                rewards = data.rewards
                redemptions = api.redemptions().data
                shopError = null
            } catch (e: Exception) {
                if (!silent || rewards.isEmpty()) shopError = e.toApiError().message
            } finally {
                shopLoading = false
            }
        }
    }

    /**
     * Wymiana punktów. [idempotencyKey] tworzy ekran raz na potwierdzenie –
     * ponowienie po błędzie sieci z tym samym kluczem nie pobierze punktów drugi raz.
     */
    fun redeem(reward: Reward, idempotencyKey: String, onDone: (Boolean) -> Unit) {
        if (busy) return
        busy = true
        viewModelScope.launch {
            try {
                val result = api.redeem(reward.id, mapOf("idempotency_key" to idempotencyKey)).data
                balance = result.balance
                lastRedemption = result.redemption
                rewards = api.rewards().data.rewards
                redemptions = api.redemptions().data
                onDone(true)
            } catch (e: Exception) {
                message = e.toApiError().message
                onDone(false)
            } finally {
                busy = false
            }
        }
    }

    fun newIdempotencyKey(): String = UUID.randomUUID().toString()

    fun toggleDemoMode(enabled: Boolean) {
        if (enabled && !demoAvailable) return
        demoMode = enabled
        store.demoMode = enabled
        if (!enabled) simulatedLocation = null
    }

    // ------------------------------------------------------------------------
    //  Rozgrywka
    // ------------------------------------------------------------------------

    /** Czy gracz ma już trwającą rozgrywkę tej gry (wznowienie nie zużywa limitu). */
    fun isInProgress(gameId: Int): Boolean =
        profile?.history?.any { it.gameId == gameId && it.status == "in_progress" } == true

    /** „Rozpocznij grę” – najpierw podsumowanie z aktualnym limitem (wznowienie – od razu). */
    fun requestStart(gameId: Int) {
        if (isInProgress(gameId)) {
            play(gameId)
            return
        }
        confirmStartGameId = gameId
        viewModelScope.launch {
            try {
                limits = api.limits().data
            } catch (e: Exception) {
                // podsumowanie pokaże się bez limitu; serwer i tak go sprawdzi
            }
        }
    }

    /** Start gry albo powrót do trwającej rozgrywki. */
    fun play(gameId: Int) {
        confirmStartGameId = null
        if (busy) return
        busy = true
        viewModelScope.launch {
            try {
                if (game?.id != gameId) game = api.game(gameId).data
                session = api.startSession(gameId).data
                selectedWaypointId = null
                route = Route.Play(gameId)
                loadProfile() // zużyty limit dnia, „Kontynuuj” w szczegółach gry
            } catch (e: Exception) {
                message = e.toApiError().message
            } finally {
                busy = false
            }
        }
    }

    // --- prowadzenie do celu (trasa po drogach i ścieżkach) ------------------
    var navRoute by mutableStateOf<NavRoute?>(null)
        private set
    var routeLoading by mutableStateOf(false)
        private set
    var routeMode by mutableStateOf(RouteMode.FOOT)
        private set
    /** Każde nowe wyznaczenie trasy – ekran dopasowuje do niej mapę. */
    var routeVersion by mutableStateOf(0)
        private set
    /** Gracz zamknął prowadzenie krzyżykiem – nie włączamy go ponownie automatycznie. */
    var routeDismissed by mutableStateOf(false)
        private set
    private var routeJob: Job? = null
    private var routeFrom: PlayerLocation? = null
    private var routeAt = 0L

    /** „Prowadź” – wyznacza trasę od gracza do celu i rysuje ją na mapie. */
    fun startRoute(
        from: PlayerLocation?, toLat: Double, toLng: Double, name: String,
        mode: RouteMode = routeMode,
        /** automatyczne wyznaczenie (start gry, nowy cel) – bez komunikatów o błędach */
        silent: Boolean = false,
    ) {
        if (from == null) {
            if (!silent) message = "Szukam Twojej pozycji GPS – spróbuj za chwilę."
            return
        }
        routeDismissed = false
        routeMode = mode
        routeJob?.cancel()
        routeLoading = true
        routeFrom = from
        routeAt = System.currentTimeMillis()
        routeJob = viewModelScope.launch {
            try {
                navRoute = RoutingClient.route(mode, from.latitude, from.longitude, toLat, toLng, name)
                routeVersion++
            } catch (e: kotlinx.coroutines.CancellationException) {
                throw e
            } catch (e: Exception) {
                // bez serwera tras – linia prosta do celu; followRoute spróbuje ponownie za 20 s
                val hadRoute = navRoute?.approximate == false
                navRoute = straightRoute(mode, from.latitude, from.longitude, toLat, toLng, name)
                if (!hadRoute) routeVersion++
                if (!silent) message = "Brak trasy po drogach (sprawdź internet w telefonie) – pokazuję kierunek w linii prostej."
            } finally {
                routeLoading = false
            }
        }
    }

    fun changeRouteMode(mode: RouteMode, from: PlayerLocation?) {
        val r = navRoute ?: run { routeMode = mode; return }
        startRoute(from, r.targetLat, r.targetLng, r.targetName, mode)
    }

    /** @param byUser krzyżyk na pasku trasy – trasa nie wróci sama przy kolejnym punkcie */
    fun stopRoute(byUser: Boolean = false) {
        if (byUser) routeDismissed = true
        routeJob?.cancel()
        routeLoading = false
        navRoute = null
    }

    /**
     * Gracz zszedł z trasy (> 40 m od linii) – wyznaczamy nową, najwyżej co 20 s.
     * Wywoływane przy każdej zmianie pozycji.
     */
    fun followRoute(location: PlayerLocation?) {
        val r = navRoute ?: return
        if (location == null || routeLoading) return
        if (System.currentTimeMillis() - routeAt < 20_000) return
        if (r.approximate) {
            // linia prosta – ponowna próba wyznaczenia trasy po drogach
            startRoute(location, r.targetLat, r.targetLng, r.targetName, silent = true)
            return
        }
        val off = r.distanceFrom(location.latitude, location.longitude)
        if (off > 40 + location.accuracyM.coerceAtMost(30f)) {
            startRoute(location, r.targetLat, r.targetLng, r.targetName, silent = true)
        }
    }

    /** Aktualna pozycja: z GPS albo – w trybie demo – ustawiona na mapie. */
    fun effectiveLocation(real: PlayerLocation?): PlayerLocation? =
        if (demoMode) simulatedLocation ?: real else real

    /**
     * „Jestem na miejscu” – serwer sprawdza odległość od punktu.
     * @return false, gdy zgłoszenie nie zostało wysłane (trwa inne)
     */
    fun arrive(waypointId: Int, location: PlayerLocation, silent: Boolean = false): Boolean {
        val current = session ?: return false
        if (arriving) return false
        arriving = true
        viewModelScope.launch {
            try {
                val body = mapOf<String, Any>(
                    "latitude" to location.latitude,
                    "longitude" to location.longitude,
                    "accuracy_m" to location.accuracyM.toInt(),
                )
                val updated = api.arrive(current.id, waypointId, body).data
                session = updated
                arrivals++
                val name = game?.waypoints?.firstOrNull { it.id == waypointId }?.name ?: "punktu"
                message = if (updated.stateOf(waypointId)?.task?.available == true) {
                    "Dotarłeś do: $name. Zadanie jest odblokowane!"
                } else {
                    "Dotarłeś do: $name."
                }
                if (updated.isCompleted) onFinished()
            } catch (e: Exception) {
                if (!silent) message = e.toApiError().message
            } finally {
                arriving = false
            }
        }
        return true
    }

    /** Wysyła odpowiedź; onDone(true) zamyka okno zadania. */
    fun answer(taskId: Int, answer: Answer, onDone: (Boolean) -> Unit) {
        val current = session ?: return
        if (busy) return
        busy = true
        viewModelScope.launch {
            try {
                val updated = when (answer) {
                    is Answer.Options -> api.answer(current.id, taskId, mapOf("option_ids" to answer.optionIds)).data
                    is Answer.Text -> api.answer(current.id, taskId, mapOf("answer_text" to answer.text)).data
                    is Answer.Info -> api.answer(current.id, taskId, emptyMap()).data
                    is Answer.Media -> {
                        val part = MultipartBody.Part.createFormData(
                            "media", answer.file.name, answer.file.asRequestBody(answer.mimeType.toMediaType()),
                        )
                        val comment = answer.comment.trim().takeIf { it.isNotEmpty() }
                            ?.toRequestBody("text/plain; charset=utf-8".toMediaType())
                        api.answerWithMedia(current.id, taskId, part, comment).data.also { answer.file.delete() }
                    }
                }
                session = updated
                val submission = updated.waypoints.firstNotNullOfOrNull { wp ->
                    wp.task?.takeIf { it.taskId == taskId }?.submission
                }
                message = when {
                    submission == null -> "Odpowiedź zapisana."
                    submission.isCorrect == true -> "Brawo, dobra odpowiedź! +${submission.pointsAwarded} pkt"
                    submission.isCorrect == false -> "Niestety, to nie ta odpowiedź. 0 pkt"
                    submission.maxPoints == 0 -> "Gotowe!"
                    else -> "Zaliczone! +${submission.pointsAwarded} pkt"
                }
                if (updated.isCompleted) onFinished()
                onDone(true)
            } catch (e: Exception) {
                val error = e.toApiError()
                message = error.message
                if (error.code == 409) refreshSession() // zadanie już wykonane
                onDone(false)
            } finally {
                busy = false
            }
        }
    }

    private fun refreshSession() {
        val current = session ?: return
        viewModelScope.launch {
            try {
                session = api.session(current.id).data
            } catch (e: Exception) {
                // zostaje poprzedni stan
            }
        }
    }

    private fun onFinished() {
        locationTracker.stop()
        loadProfile() // nowe saldo punktów i historia
        val gameId = session?.gameId ?: return
        viewModelScope.launch {
            try {
                gameRanking = api.gameRanking(gameId).data
            } catch (e: Exception) {
                // ranking pokaże się przy kolejnym wejściu
            }
        }
    }

    override fun onCleared() {
        locationTracker.stop()
    }
}

/** Grupy miejsc w zakładce „Odkrywaj” (slugi kategorii z bazy). */
data class PlaceGroup(val label: String, val icon: String, val slugs: List<String>)

val PLACE_GROUPS = listOf(
    PlaceGroup("Zabytki", PgmIcons.MONUMENT, listOf("zabytek-rejestr", "zabytek-ewidencja")),
    PlaceGroup("Parki", PgmIcons.PARK, listOf("park-skwer", "fontanna")),
    PlaceGroup("Rower miejski", PgmIcons.BIKE, listOf("rower-miejski")),
    PlaceGroup("Rekreacja", PgmIcons.PLAYGROUND, listOf("plac-zabaw", "silownia-plenerowa", "boisko-rekreacja")),
    PlaceGroup("Przystanki", PgmIcons.BUS, listOf("przystanek")),
    PlaceGroup("Szkoły", PgmIcons.SCHOOL, listOf(
        "przedszkole", "szkola-podstawowa", "szkola-ponadpodstawowa", "szkola-specjalna",
        "szkola-dla-doroslych", "szkola-wyzsza", "placowka-pozaszkolna", "poradnia", "internat",
    )),
    PlaceGroup("Toalety i parkingi", PgmIcons.WC, listOf("toaleta", "parking")),
    PlaceGroup("Osiedla", PgmIcons.HOME, listOf("osiedle")),
)

/** Ikona (ścieżka z [PgmIcons]) dla kategorii miejsca. */
fun placeIcon(category: String): String = when {
    category.startsWith("zabytek") -> PgmIcons.MONUMENT
    category.startsWith("szkola") || category in setOf("przedszkole", "poradnia", "placowka-pozaszkolna", "internat") -> PgmIcons.SCHOOL
    category == "przystanek" -> PgmIcons.BUS
    category == "rower-miejski" -> PgmIcons.BIKE
    category in setOf("plac-zabaw", "silownia-plenerowa", "boisko-rekreacja") -> PgmIcons.PLAYGROUND
    category in setOf("park-skwer", "fontanna") -> PgmIcons.PARK
    category == "toaleta" -> PgmIcons.WC
    category == "parking" -> PgmIcons.PARKING
    category == "osiedle" -> PgmIcons.HOME
    else -> PgmIcons.PIN
}

/** Czy pozycja jest w okolicy Płocka (emulator domyślnie „stoi” w Kalifornii). */
fun PlayerLocation.isNearPlock(): Boolean =
    distanceMeters(latitude, longitude, pl.plock.pgm.Config.PLOCK_LAT, pl.plock.pgm.Config.PLOCK_LNG) < 30_000
