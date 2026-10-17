package pl.plock.pgm.data

/*
 * Dane z API serwera PGM (opis: docs/API-android.md).
 * Gson zamienia nazwy z JSON-a ("short_description") na pola Kotlina
 * ("shortDescription") – ustawione w ApiClient (LOWER_CASE_WITH_UNDERSCORES).
 */

/** Każda poprawna odpowiedź serwera ma postać {"data": ...}. */
data class ApiResponse<T>(val data: T)

data class Category(
    val slug: String,
    val name: String,
)

data class LatLng(
    val latitude: Double,
    val longitude: Double,
)

/** Gra na liście. */
data class GameSummary(
    val id: Int,
    val title: String,
    val shortDescription: String?,
    val difficulty: String,
    val estimatedDurationMinutes: Int?,
    val completionPoints: Int,
    val maxScore: Int,
    val isSample: Boolean,
    val categories: List<Category>?,
    val waypointCount: Int,
    val routeLengthM: Int,
    val start: LatLng?,
)

/** Gra ze wszystkimi punktami i zadaniami (bez poprawnych odpowiedzi). */
data class GameDetail(
    val id: Int,
    val title: String,
    val shortDescription: String?,
    val description: String?,
    val difficulty: String,
    val estimatedDurationMinutes: Int?,
    val completionPoints: Int,
    val maxScore: Int,
    val isSample: Boolean,
    val categories: List<Category>?,
    val waypointCount: Int,
    val routeLengthM: Int,
    val waypoints: List<Waypoint>,
)

data class Waypoint(
    val id: Int,
    val position: Int,
    val name: String,
    val description: String?,
    val playerHint: String?,
    val latitude: Double,
    val longitude: Double,
    val arrivalRadiusM: Int,
    val task: GameTask?,
)

data class GameTask(
    val id: Int,
    /** single_choice, multiple_choice, open_answer, photo, video, info */
    val taskType: String,
    val title: String,
    val description: String?,
    val hint: String?,
    val maxPoints: Int,
    val mediaRequirement: String?,
    val mediaMaxSeconds: Int?,
    /** always, on_arrival, after_previous */
    val unlockMode: String,
    val options: List<TaskOption>?,
)

data class TaskOption(
    val id: Int,
    val text: String,
)

/** Stan rozgrywki – zawsze liczony przez serwer. */
data class SessionState(
    val id: Int,
    val gameId: Int,
    /** in_progress, completed */
    val status: String,
    val score: Int,
    val taskPoints: Int,
    val completionPoints: Int,
    val maxScore: Int,
    val completedWaypoints: Int,
    val totalWaypoints: Int,
    val nextWaypointId: Int?,
    val startedAt: String?,
    val finishedAt: String?,
    val waypoints: List<WaypointState>,
) {
    val isCompleted: Boolean get() = status == "completed"

    fun stateOf(waypointId: Int): WaypointState? = waypoints.firstOrNull { it.waypointId == waypointId }
}

data class WaypointState(
    val waypointId: Int,
    val position: Int,
    val reached: Boolean,
    val reachedAt: String?,
    val completed: Boolean,
    val task: TaskState?,
)

data class TaskState(
    val taskId: Int,
    val available: Boolean,
    val submission: Submission?,
)

data class Submission(
    val isCorrect: Boolean?,
    val pointsAwarded: Int,
    val maxPoints: Int,
    /** auto, unverified, rejected */
    val reviewStatus: String,
    val submittedAt: String?,
    val correctOptionIds: List<Int>?,
    val selectedOptionIds: List<Int>?,
)

data class Player(
    val id: Int,
    val nickname: String,
    val createdAt: String?,
    /** Nick można zmienić raz w roku (pierwsza zmiana – od razu). */
    val nicknameChangeAllowed: Boolean = true,
    val nicknameNextChangeAt: String? = null,
)

/** Saldo punktów – liczone przez serwer (najlepszy wynik z każdej gry − wymiany). */
data class Balance(
    val earned: Int,
    val spent: Int,
    val balance: Int,
)

/** Limit rozpoczętych gier na dziś (czas polski, liczy serwer). */
data class DailyLimit(
    val dailyLimit: Int,
    val usedToday: Int,
    val remaining: Int,
    val resetsAt: String?,
)

data class RegisterResult(
    val player: Player,
    val token: String,
)

data class PlayerProfile(
    val player: Player,
    val balance: Balance?,
    val limits: DailyLimit?,
    val history: List<HistoryEntry>,
)

data class PlayerOnly(
    val player: Player,
)

data class HistoryEntry(
    val sessionId: Int,
    val gameId: Int,
    val gameTitle: String,
    val status: String,
    val score: Int,
    val startedAt: String?,
    val finishedAt: String?,
    val completedWaypoints: Int = 0,
    val totalWaypoints: Int = 0,
    /** false – administrator ukrył lub zarchiwizował grę (historia zostaje). */
    val gameAvailable: Boolean = true,
)

data class RankingEntry(
    val rank: Int,
    val playerId: Int,
    val nickname: String,
    val score: Int,
    val gamesCompleted: Int?,
    val finishedAt: String?,
)

/** Rodzaj miejsca z otwartych danych miasta (zabytek, przystanek…). */
data class PlaceCategory(
    val slug: String,
    val name: String,
    val sourceName: String?,
    val placeCount: Int,
)

/** Miejsce w Płocku (zakładka „Odkrywaj”). */
data class Place(
    val id: Int,
    val category: String,
    val categoryName: String,
    val name: String,
    val address: String?,
    val description: String?,
    val latitude: Double,
    val longitude: Double,
    val distanceM: Int,
)

/** Nagroda w sklepie punktów (dodaje ją administrator w panelu). */
data class Reward(
    val id: Int,
    val title: String,
    val description: String?,
    /** discount_code (kod rabatowy) albo gift_card (karta podarunkowa) – z Kreatora nagród */
    val rewardType: String,
    /** wartość do pokazania: „−10%”, „−15 zł”, „50 zł” */
    val valueLabel: String?,
    val partnerName: String?,
    val partnerUrl: String?,
    val minOrderAmount: Double?,
    /** warunki nagrody wpisane w panelu */
    val terms: String?,
    val costPoints: Int,
    /** ile kodów zostało w puli */
    val stockLeft: Int?,
    /** ile razy jeden gracz może odebrać nagrodę / ile razy jeszcze może Twój gracz */
    val perPlayerLimit: Int?,
    val playerLimitLeft: Int?,
    val validUntil: String?,
    /** nagroda demonstracyjna – bez wartości handlowej */
    val isSample: Boolean,
    val available: Boolean,
    val affordable: Boolean,
)

data class RewardsResponse(
    val balance: Balance,
    val rewards: List<Reward>,
)

/** Wymiana punktów – kod do okazania przy odbiorze. */
data class Redemption(
    val id: Int,
    val rewardId: Int,
    val title: String,
    val rewardType: String,
    val valueLabel: String?,
    val partnerName: String?,
    val partnerUrl: String?,
    val costPoints: Int,
    val code: String,
    /** issued, used, cancelled */
    val status: String,
    val redemptionInstructions: String?,
    val validUntil: String?,
    val isSample: Boolean,
    val createdAt: String?,
    val usedAt: String?,
)

data class RedeemResult(
    val redemption: Redemption,
    val balance: Balance,
)
