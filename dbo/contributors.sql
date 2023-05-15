create table contributors
(
    id               smallint identity
        constraint PK_contributers
            primary key,
    person_id        smallint     not null
        constraint FK_contributors_people
            references people,
    first_name       nvarchar(50) not null,
    last_name        nvarchar(50) not null,
    employment_no    int,
    started_at       datetime,
    finished_at      datetime,
    activity_type_id smallint
        constraint FK_contributers_activity_type
            references activity_types,
    updated_at       datetime     not null,
    created_at       datetime     not null
)
go

