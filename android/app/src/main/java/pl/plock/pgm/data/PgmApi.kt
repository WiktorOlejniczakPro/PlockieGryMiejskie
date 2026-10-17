package pl.plock.pgm.data

import com.google.gson.FieldNamingPolicy
import com.google.gson.GsonBuilder
import com.google.gson.JsonParser
import okhttp3.MultipartBody
import okhttp3.OkHttpClient
import okhttp3.RequestBody
import pl.plock.pgm.Config
import retrofit2.HttpException
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.PUT
import retrofit2.http.Part
import retrofit2.http.Path
import retrofit2.http.Query
import java.io.IOException
import java.util.concurrent.TimeUnit

/** Adresy API serwera PGM (Retrofit). */
interface PgmApi {

    @GET("categories")
    suspend fun categories(): ApiResponse<List<Category>>

    @GET("games")
    suspend fun games(
        @Query("category") category: String? = null,
        @Query("q") search: String? = null,
    ): ApiResponse<List<GameSummary>>

    @GET("games/{id}")
    suspend fun game(@Path("id") id: Int): ApiResponse<GameDetail>

    @GET("games/{id}/ranking")
    suspend fun gameRanking(@Path("id") id: Int): ApiResponse<List<RankingEntry>>

    @GET("ranking")
    suspend fun overallRanking(): ApiResponse<List<RankingEntry>>

    @GET("place-categories")
    suspend fun placeCategories(): ApiResponse<List<PlaceCategory>>

    @GET("places")
    suspend fun places(
        @Query("lat") latitude: Double?,
        @Query("lng") longitude: Double?,
        @Query("radius_m") radiusM: Int,
        @Query("category") categories: String?,
    ): ApiResponse<List<Place>>

    @POST("players")
    suspend fun register(@Body body: Map<String, String>): ApiResponse<RegisterResult>

    @GET("me")
    suspend fun me(): ApiResponse<PlayerProfile>

    @PUT("me")
    suspend fun rename(@Body body: Map<String, String>): ApiResponse<PlayerOnly>

    @GET("me/limits")
    suspend fun limits(): ApiResponse<DailyLimit>

    @GET("rewards")
    suspend fun rewards(): ApiResponse<RewardsResponse>

    @POST("rewards/{id}/redeem")
    suspend fun redeem(@Path("id") rewardId: Int, @Body body: Map<String, String>): ApiResponse<RedeemResult>

    @GET("me/redemptions")
    suspend fun redemptions(): ApiResponse<List<Redemption>>

    @POST("games/{id}/sessions")
    suspend fun startSession(@Path("id") gameId: Int): ApiResponse<SessionState>

    @GET("sessions/{id}")
    suspend fun session(@Path("id") sessionId: Int): ApiResponse<SessionState>

    @POST("sessions/{id}/waypoints/{waypointId}/arrival")
    suspend fun arrive(
        @Path("id") sessionId: Int,
        @Path("waypointId") waypointId: Int,
        @Body body: Map<String, @JvmSuppressWildcards Any>,
    ): ApiResponse<SessionState>

    @POST("sessions/{id}/tasks/{taskId}/answer")
    suspend fun answer(
        @Path("id") sessionId: Int,
        @Path("taskId") taskId: Int,
        @Body body: Map<String, @JvmSuppressWildcards Any>,
    ): ApiResponse<SessionState>

    @Multipart
    @POST("sessions/{id}/tasks/{taskId}/answer")
    suspend fun answerWithMedia(
        @Path("id") sessionId: Int,
        @Path("taskId") taskId: Int,
        @Part media: MultipartBody.Part,
        @Part("answer_text") comment: RequestBody?,
    ): ApiResponse<SessionState>
}

object ApiClient {

    /** Klient z tokenem gracza dołączanym do każdego żądania. */
    fun create(baseUrl: String, tokenProvider: () -> String?): PgmApi {
        val http = OkHttpClient.Builder()
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(60, TimeUnit.SECONDS)
            .writeTimeout(120, TimeUnit.SECONDS) // wysyłanie filmów
            .addInterceptor { chain ->
                val request = chain.request().newBuilder().header("Accept", "application/json")
                tokenProvider()?.let { request.header("Authorization", "Bearer $it") }
                chain.proceed(request.build())
            }
            .build()

        val gson = GsonBuilder()
            .setFieldNamingPolicy(FieldNamingPolicy.LOWER_CASE_WITH_UNDERSCORES)
            .create()

        return Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(http)
            .addConverterFactory(GsonConverterFactory.create(gson))
            .build()
            .create(PgmApi::class.java)
    }
}

/**
 * Czy pod tym adresem odpowiada API PGM (szybki test, 3 s).
 * Wywoływać poza wątkiem głównym.
 */
fun probeServer(baseUrl: String): Boolean = try {
    val http = OkHttpClient.Builder()
        .connectTimeout(3, TimeUnit.SECONDS)
        .readTimeout(4, TimeUnit.SECONDS)
        .build()
    val request = okhttp3.Request.Builder().url(baseUrl + "categories").header("Accept", "application/json").build()
    http.newCall(request).execute().use { response ->
        response.isSuccessful && (response.body?.string()?.trimStart()?.startsWith("{") == true)
    }
} catch (e: Exception) {
    false
}

/** Błąd do pokazania graczowi. code = kod HTTP (0 = brak połączenia). */
class ApiError(message: String, val code: Int) : Exception(message)

/** Zamienia wyjątek sieci/serwera na czytelny komunikat po polsku. */
fun Throwable.toApiError(): ApiError = when (this) {
    is ApiError -> this
    is HttpException -> {
        val body = try {
            response()?.errorBody()?.string()
        } catch (e: IOException) {
            null
        }
        val message = try {
            JsonParser.parseString(body ?: "").asJsonObject
                .getAsJsonObject("error").get("message").asString
        } catch (e: Exception) {
            null
        }
        ApiError(message ?: "Błąd serwera (${code()}). Spróbuj ponownie.", code())
    }
    is IOException -> ApiError("Brak połączenia z serwerem. Sprawdź połączenie i spróbuj ponownie.", 0)
    else -> ApiError(message ?: "Nieoczekiwany błąd.", -1)
}
