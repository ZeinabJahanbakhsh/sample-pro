create table grades
(
    id         smallint not null
        constraint grades_pk
            primary key,
    name       nvarchar(50),
    created_at datetime not null,
    updated_at datetime not null
)
go

