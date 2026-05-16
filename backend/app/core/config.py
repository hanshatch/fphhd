from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")

    database_url: str = "postgresql+asyncpg://fp_user:fp_password@localhost:5432/fp_db"
    database_url_sync: str = "postgresql://fp_user:fp_password@localhost:5432/fp_db"

    secret_key: str = "dev-secret-change-in-production"
    access_token_expire_minutes: int = 30
    cookie_secure: bool = False
    cookie_domain: str = "localhost"

    totp_issuer: str = "FP Finanzas Personales"

    app_env: str = "development"
    debug: bool = True
    allowed_origins: list[str] = ["http://localhost:5173"]

    smtp_enabled: bool = False
    smtp_host: str = ""
    smtp_port: int = 587
    smtp_user: str = ""
    smtp_password: str = ""
    smtp_from: str = "noreply@fp.hanshatch.com"


settings = Settings()
